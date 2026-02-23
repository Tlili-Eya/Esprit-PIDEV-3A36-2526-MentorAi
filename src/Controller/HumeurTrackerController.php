<?php

namespace App\Controller;

use App\Entity\Humeur;
use App\Entity\ProfilApprentissage;
use App\Repository\HumeurRepository;use App\Repository\ProfilApprentissageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/mood-tracker', name: 'mood_tracker_')]
final class HumeurTrackerController extends AbstractController
{
    private const MOOD_LABELS = [
        1 => 'Très Triste',
        2 => 'Triste',
        3 => 'Neutre',
        4 => 'Heureux',
        5 => 'Très Heureux'
    ];

    private const MOOD_EMOJIS = [
        1 => '😢',
        2 => '😔',
        3 => '😐',
        4 => '😊',
        5 => '😄'
    ];

    private const MOOD_COLORS = [
        1 => '#FF6B6B',
        2 => '#9B59B6',
        3 => '#3498DB',
        4 => '#2ECC71',
        5 => '#F1C40F'
    ];

    #[Route('', name: 'dashboard')]
    public function dashboard(
        HumeurRepository $humeurRepository,
        ProfilApprentissageRepository $profilRepository
    ): Response {
        // Get current user's profile (you may need to adjust this based on your authentication)
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

        return $this->render('front/mood-tracker.html.twig', [
            'todayMood' => $todayMood,
            'moods' => $moods,
            'averageMood' => $averageMood,
            'longestStreak' => $longestStreak,
            'moodLabels' => self::MOOD_LABELS,
            'moodEmojis' => self::MOOD_EMOJIS,
            'moodColors' => self::MOOD_COLORS,
            'userName' => $profil?->getUtilisateur()?->getPrenom()
                ?? $profil?->getUtilisateur()?->getNom()
                ?? 'User',
        ]);
    }

    #[Route('/log', name: 'log', methods: ['POST'])]
    public function logMood(
        Request $request,
        EntityManagerInterface $entityManager,
        HumeurRepository $humeurRepository,
        ProfilApprentissageRepository $profilRepository
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validation
            $errors = $this->validateMoodData($data);
            if (!empty($errors)) {
                return new JsonResponse(['success' => false, 'errors' => $errors], 400);
            }

            $profil = $profilRepository->findOneBy([]);  // TODO: Get actual user profile
            
            if (!$profil) {
                return new JsonResponse(['success' => false, 'error' => 'Profile not found'], 404);
            }

            // Check if mood already exists for today
            $existingMood = $humeurRepository->getTodayMood($profil);
            
            if ($existingMood) {
                return new JsonResponse([
                    'success' => false, 
                    'error' => 'Mood already logged for today. Use update endpoint instead.'
                ], 400);
            }

            // Create new mood entry
            $humeur = new Humeur();
            $humeur->setValeurHumeur($data['mood']);
            $humeur->setTendance(implode(',', $data['tags'] ?? []));
            $humeur->setFacteurPrincipal($data['journal'] ?? '');
            $humeur->setCreeLe(new \DateTime());
            $humeur->setProfilApprentissage($profil);

            // Calculate averages
            $this->updateAverages($humeur, $humeurRepository, $profil);

            $entityManager->persist($humeur);
            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'mood' => [
                    'id' => $humeur->getId(),
                    'valeur' => $humeur->getValeurHumeur(),
                    'label' => self::MOOD_LABELS[$humeur->getValeurHumeur()],
                    'emoji' => self::MOOD_EMOJIS[$humeur->getValeurHumeur()],
                    'tags' => $data['tags'] ?? [],
                    'sleep' => $data['sleep'] ?? null,
                    'journal' => $humeur->getFacteurPrincipal(),
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/update/{id}', name: 'update', methods: ['POST'])]
    public function updateMood(
        int $id,
        Request $request,
        HumeurRepository $humeurRepository,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            $data = json_decode($request->getContent(), true);
            
            // Validation
            $errors = $this->validateMoodData($data);
            if (!empty($errors)) {
                return new JsonResponse(['success' => false, 'errors' => $errors], 400);
            }

            $humeur = $humeurRepository->find($id);
            
            if (!$humeur) {
                return new JsonResponse(['success' => false, 'error' => 'Mood entry not found'], 404);
            }

            // Update mood entry
            $humeur->setValeurHumeur($data['mood']);
            $humeur->setTendance(implode(',', $data['tags'] ?? []));
            $humeur->setFacteurPrincipal($data['journal'] ?? '');

            // Recalculate averages
            $this->updateAverages($humeur, $humeurRepository, $humeur->getProfilApprentissage());

            $entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'mood' => [
                    'id' => $humeur->getId(),
                    'valeur' => $humeur->getValeurHumeur(),
                    'label' => self::MOOD_LABELS[$humeur->getValeurHumeur()],
                    'emoji' => self::MOOD_EMOJIS[$humeur->getValeurHumeur()],
                    'tags' => $data['tags'] ?? [],
                    'sleep' => $data['sleep'] ?? null,
                    'journal' => $humeur->getFacteurPrincipal(),
                ]
            ]);

        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/data', name: 'data', methods: ['GET'])]
    public function getMoodData(
        HumeurRepository $humeurRepository,
        ProfilApprentissageRepository $profilRepository
    ): JsonResponse {
        $profil = $profilRepository->findOneBy([]);  // TODO: Get actual user profile
        
        if (!$profil) {
            return new JsonResponse(['success' => false, 'error' => 'Profile not found'], 404);
        }

        $moods = $humeurRepository->getLastNDaysMoods($profil, 14);
        $todayMood = $humeurRepository->getTodayMood($profil);

        $chartData = array_map(function($humeur) {
            return [
                'date' => $humeur->getCreeLe()->format('M d'),
                'mood' => $humeur->getValeurHumeur(),
                'label' => self::MOOD_LABELS[$humeur->getValeurHumeur()],
                'color' => self::MOOD_COLORS[$humeur->getValeurHumeur()],
            ];
        }, $moods);

        return new JsonResponse([
            'success' => true,
            'chartData' => $chartData,
            'todayMood' => $todayMood ? [
                'id' => $todayMood->getId(),
                'valeur' => $todayMood->getValeurHumeur(),
                'label' => self::MOOD_LABELS[$todayMood->getValeurHumeur()],
                'emoji' => self::MOOD_EMOJIS[$todayMood->getValeurHumeur()],
                'tags' => explode(',', $todayMood->getTendance() ?? ''),
                'journal' => $todayMood->getFacteurPrincipal(),
            ] : null,
        ]);
    }

    private function validateMoodData(array $data): array
    {
        $errors = [];

        // Validate mood value (1-5)
        if (!isset($data['mood']) || $data['mood'] < 1 || $data['mood'] > 5) {
            $errors[] = 'Mood value must be between 1 and 5';
        }

        // Validate tags (must select at least 1, max 3)
        if (isset($data['tags'])) {
            if (!is_array($data['tags'])) {
                $errors[] = 'Tags must be an array';
            } elseif (count($data['tags']) > 3) {
                $errors[] = 'Maximum 3 tags allowed';
            }
        }

        // Validate journal (max 150 characters)
        if (isset($data['journal']) && strlen($data['journal']) > 150) {
            $errors[] = 'Journal entry cannot exceed 150 characters';
        }

        return $errors;
    }

    private function updateAverages(
        Humeur $humeur,
        HumeurRepository $humeurRepository,
        ?ProfilApprentissage $profil
    ): void {
        if (!$profil) {
            return;
        }

        $avg7Days = $humeurRepository->getAverageMood($profil, 7);
        $avg30Days = $humeurRepository->getAverageMood($profil, 30);

        $humeur->setMoyenne7j($avg7Days);
        $humeur->setMoyenne30j($avg30Days);

        // Determine risk level based on mood value
        $niveauRisque = match(true) {
            $humeur->getValeurHumeur() <= 2 => 'High',
            $humeur->getValeurHumeur() == 3 => 'Medium',
            default => 'Low'
        };
        $humeur->setNiveauRisque($niveauRisque);
    }
}
