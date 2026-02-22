<?php

namespace App\Controller;

use App\Repository\HumeurRepository;
use App\Repository\ProfilApprentissageRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/', name: 'front_')]
final class FrontController extends AbstractController
{
    #[Route('', name: 'home')]
    public function home(): Response
    {
        return $this->render('front/home.html.twig');
    }

    #[Route('about', name: 'about')]
    public function about(): Response
    {
        return $this->render('front/about.html.twig');
    }

    #[Route('courses', name: 'courses')]
    public function courses(): Response
    {
        return $this->render('front/courses.html.twig');
    }

    #[Route('course-details', name: 'course_details')]
    public function courseDetails(): Response
    {
        return $this->render('front/course-details.html.twig');
    }

    #[Route('instructors', name: 'instructors')]
    public function instructors(): Response
    {
        return $this->render('front/instructors.html.twig');
    }

    #[Route('instructor-profile', name: 'instructor_profile')]
    public function instructorProfile(
        HumeurRepository $humeurRepository,
        ProfilApprentissageRepository $profilRepository
    ): Response {
        // Mood tracker data
        $profil = $profilRepository->findOneBy([]);  // TODO: Get actual user profile
        
        $todayMood = null;
        $moods = [];
        $averageMood = null;
        $longestStreak = 0;
        
        if ($profil) {
            $todayMood = $humeurRepository->getTodayMood($profil);
            $moods = $humeurRepository->getLastNDaysMoods($profil, 14);
            $averageMood = $humeurRepository->getAverageMood($profil, 7);
            $longestStreak = $humeurRepository->getLongestStreak($profil);
        }

        $moodLabels = [
            1 => 'Très Triste',
            2 => 'Triste',
            3 => 'Neutre',
            4 => 'Heureux',
            5 => 'Très Heureux'
        ];

        $moodEmojis = [
            1 => '😢',
            2 => '😔',
            3 => '😐',
            4 => '😊',
            5 => '😄'
        ];

        $moodColors = [
            1 => '#FF6B6B',
            2 => '#9B59B6',
            3 => '#3498DB',
            4 => '#2ECC71',
            5 => '#F1C40F'
        ];

        return $this->render('front/instructor-profile.html.twig', [
            'todayMood' => $todayMood,
            'moods' => $moods,
            'averageMood' => $averageMood,
            'longestStreak' => $longestStreak,
            'moodLabels' => $moodLabels,
            'moodEmojis' => $moodEmojis,
            'moodColors' => $moodColors,
            'userName' => $profil?->getUtilisateur()?->getPrenom()
                ?? $profil?->getUtilisateur()?->getNom()
                ?? 'User',
        ]);
    }

    #[Route('events', name: 'events')]
    public function events(): Response
    {
        return $this->render('front/events.html.twig');
    }

    #[Route('pricing', name: 'pricing')]
    public function pricing(): Response
    {
        return $this->render('front/pricing.html.twig');
    }

    #[Route('privacy', name: 'privacy')]
    public function privacy(): Response
    {
        return $this->render('front/privacy.html.twig');
    }

    #[Route('terms', name: 'terms')]
    public function terms(): Response
    {
        return $this->render('front/terms.html.twig');
    }

// ⚠️ blog is now handled by CarnetController
// ⚠️ blog-details is now handled by PlanningEtudeController
    #[Route('contact', name: 'contact')]
    public function contact(): Response
    {
        return $this->render('front/contact.html.twig');
    }

    #[Route('enroll', name: 'enroll')]
    public function enroll(): Response
    {
        return $this->render('front/enroll.html.twig');
    }

    #[Route('starter', name: 'starter')]
    public function starter(): Response
    {
        return $this->render('front/starter-page.html.twig');
    }

    #[Route('404', name: '404')]
    public function error404(): Response
    {
        return $this->render('front/404.html.twig');
    }
}
