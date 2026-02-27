<?php

namespace App\Controller\Admin;

use App\Repository\UtilisateurRepository;
use App\Service\HuggingFaceRiskAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/ai-risk', name: 'admin_ai_risk_')]
final class AiRiskController extends AbstractController
{
    public function __construct(
        private readonly UtilisateurRepository  $utilisateurRepository,
        private readonly HuggingFaceRiskAnalyzer $analyzer,
        private readonly EntityManagerInterface  $em,
    ) {}

    /** Page principale : liste des utilisateurs */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/ai_risk/index.html.twig', [
            'utilisateurs' => $this->utilisateurRepository->findAll(),
        ]);
    }

    /** Analyse IA d'un utilisateur (AJAX POST) */
    #[Route('/analyze/{id}', name: 'analyze', methods: ['POST'])]
    public function analyze(int $id): JsonResponse
    {
        $user = $this->utilisateurRepository->find($id);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur introuvable'], 404);
        }

        try {
            $verdict = $this->analyzer->analyze($user);
            $user->setAiVerdict($verdict);
            $this->em->flush();

            return new JsonResponse([
                'success' => true,
                'verdict' => $verdict,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /** Analyse IA de TOUS les utilisateurs (AJAX POST) */
    #[Route('/analyze-all', name: 'analyze_all', methods: ['POST'])]
    public function analyzeAll(): JsonResponse
    {
        $users   = $this->utilisateurRepository->findAll();
        $results = [];

        foreach ($users as $user) {
            try {
                $verdict = $this->analyzer->analyze($user);
                $user->setAiVerdict($verdict);
                $results[$user->getId()] = ['success' => true, 'verdict' => $verdict];
            } catch (\Throwable $e) {
                $results[$user->getId()] = ['success' => false, 'message' => $e->getMessage()];
            }
        }

        $this->em->flush();

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $failCount    = count($results) - $successCount;

        return new JsonResponse([
            'success'      => true,
            'results'      => $results,
            'successCount' => $successCount,
            'failCount'    => $failCount,
        ]);
    }
}
