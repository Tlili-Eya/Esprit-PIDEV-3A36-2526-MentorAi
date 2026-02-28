<?php

namespace App\Controller;

use App\Repository\UtilisateurRepository;
use App\Service\UserRiskAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/users', name: 'admin_users_')]
final class AdminUserController extends AbstractController
{
    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly UserRiskAnalyzer      $analyzer,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /** Dashboard — list all users with risk data */
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $users = $this->utilisateurRepository->findAll();

        $stats = ['LOW' => 0, 'MEDIUM' => 0, 'HIGH' => 0, 'duplicates' => 0];
        foreach ($users as $u) {
            $stats[$u->getRiskLevel()]++;
            if ($u->isFlaggedDuplicate()) {
                $stats['duplicates']++;
            }
        }

        return $this->render('admin/users/index.html.twig', [
            'utilisateurs' => $users,
            'stats'        => $stats,
        ]);
    }

    /** Recalculate ALL users (AJAX) */
    #[Route('/recalculate-all', name: 'recalculate_all', methods: ['POST'])]
    public function recalculateAll(): JsonResponse
    {
        $counts = $this->analyzer->analyzeAll();

        return new JsonResponse(['success' => true, 'counts' => $counts]);
    }

    /** Recalculate a SINGLE user (AJAX) */
    #[Route('/{id}/recalculate', name: 'recalculate', methods: ['POST'])]
    public function recalculate(int $id): JsonResponse
    {
        $user = $this->utilisateurRepository->find($id);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        $this->analyzer->analyze($user);
        $this->entityManager->flush();

        return new JsonResponse([
            'success'          => true,
            'trustScore'       => $user->getTrustScore(),
            'riskLevel'        => $user->getRiskLevel(),
            'flaggedDuplicate' => $user->isFlaggedDuplicate(),
        ]);
    }

    /** Suspend a user (AJAX) */
    #[Route('/{id}/suspend', name: 'suspend', methods: ['POST'])]
    public function suspend(int $id): JsonResponse
    {
        $user = $this->utilisateurRepository->find($id);
        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'User not found'], 404);
        }

        $user->setStatus('desactiver');
        $this->entityManager->flush();

        return new JsonResponse(['success' => true]);
    }
}
