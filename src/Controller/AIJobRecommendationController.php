<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Service\JobRecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/ai')]
class AIJobRecommendationController extends AbstractController
{
    #[Route('/job-recommendations', name: 'app_ai_job_recommendations')]
    public function index(): Response
    {
        return $this->render('front/ai-embauche.html.twig');
    }

    #[Route('/api/recommendations', name: 'api_job_recommendations', methods: ['GET'])]
    public function getRecommendations(JobRecommendationService $recommendationService): JsonResponse
    {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(
                ['error' => 'User not logged in'], 
                Response::HTTP_UNAUTHORIZED
            );
        }

        // 1️⃣ Generate Profile
        $profile = $recommendationService->generateUserProfile($user);
        
        // 2️⃣ Fetch Jobs (Dynamic keywords based on user's projects)
        $technos = [];
        foreach ($user->getProjets() as $projet) {
            $technologies = $projet->getTechnologies();
            if ($technologies) {
                foreach (explode(',', $technologies) as $t) {
                    $t = trim($t);
                    if ($t !== '') {
                        $technos[] = $t;
                    }
                }
            }
        }

        $techString = !empty($technos)
            ? implode(' ', array_slice(array_unique($technos), 0, 2))
            : '';
        $keywords = "développeur " . $techString;

        $jobs = $recommendationService->fetchJobs(trim($keywords), 'france', 15);
        
        // Fallback if no specific jobs found
        if (empty($jobs) && $techString !== '') {
            $jobs = $recommendationService->fetchJobs("développeur", 'france', 15);
        }
        
        // 3️⃣ Rank Jobs using AI logic
        $rankedJobs = $recommendationService->rankJobs($profile, $jobs);

        // 4️⃣ Get Career Suggestions
        $suggestions = $recommendationService->getCareerSuggestions($user);

        return new JsonResponse([
            'jobs' => $rankedJobs,
            'suggestions' => $suggestions
        ]);
    }
}