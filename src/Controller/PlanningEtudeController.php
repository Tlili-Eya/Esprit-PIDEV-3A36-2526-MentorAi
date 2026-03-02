<?php

namespace App\Controller;

use App\Entity\PlanningEtude;
use App\Repository\PlanningEtudeRepository;
use App\Repository\ProfilApprentissageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class PlanningEtudeController extends AbstractController
{
    #[Route('/blog-details', name: 'front_blog_details', methods: ['GET'])]
    public function blogDetails(
        Request $request,
        PlanningEtudeRepository $planningRepo,
        ProfilApprentissageRepository $profilRepo
    ): Response
    {
        $selectedDate = $this->resolveSelectedDate($request->query->get('date'));

        $types = $planningRepo->findDistinctTypesWithColor();
        
        // Get the latest personality profile
        $profile = $profilRepo->findOneBy([], ['id' => 'DESC']);

        return $this->render('front/blog-details.html.twig', [
            'selectedDate' => $selectedDate,
            'types'        => $types,
            'profile'      => $profile,
        ]);
    }

    #[Route('/planning/week', name: 'front_planning_week', methods: ['GET'])]
    public function weekData(
        Request $request,
        PlanningEtudeRepository $planningRepo
    ): JsonResponse {
        $selectedDate = $this->resolveSelectedDate($request->query->get('date'));

        $weekStart = (clone $selectedDate)->modify('monday this week');
        $weekEnd = (clone $weekStart)->modify('sunday this week');

        $plannings = $planningRepo->findByDateRange($weekStart, $weekEnd);
        $types = $planningRepo->findDistinctTypesWithColor();

        $activities = array_map(static function (PlanningEtude $planning): array {
            return [
                'id' => $planning->getId(),
                'titre' => $planning->getTitreP(),
                'date' => $planning->getDateSeance()?->format('Y-m-d'),
                'heure_debut' => $planning->getHeureDebut()?->format('H:i'),
                'duree_prevue' => $planning->getDureePrevue(),
                'type_activite' => $planning->getTypeActivite(),
                'couleur_activite' => $planning->getCouleurActivite() ?? '#dfe6e9',
                'etat' => $planning->getEtat(),
                'notes_pers' => $planning->getNotesPers(),
            ];
        }, $plannings);

        return new JsonResponse([
            'selectedDate' => $selectedDate->format('Y-m-d'),
            'weekStart' => $weekStart->format('Y-m-d'),
            'weekEnd' => $weekEnd->format('Y-m-d'),
            'types' => $types,
            'activities' => $activities,
        ]);
    }

    #[Route('/planning/create', name: 'front_planning_create', methods: ['POST'])]
    public function createActivity(
        Request $request,
        EntityManagerInterface $em,
        PlanningEtudeRepository $planningRepo
    ): JsonResponse {
        $errors = [];

        $dateString = $request->request->get('date');
        $selectedDate = $this->parseDateOrNull($dateString);
        if (!$selectedDate) {
            $errors[] = 'La date de séance est obligatoire.';
        }

        $titre = trim((string) $request->request->get('titre_p'));
        $heureDebut = (string) $request->request->get('heure_debut');
        $dureePrevue = $this->parseDurationFields(
            $request->request->get('duree_heures'),
            $request->request->get('duree_minutes')
        );
        $typeActivite = trim((string) $request->request->get('type_activite'));
        $couleur = trim((string) $request->request->get('couleur_activite'));
        $notes = trim((string) $request->request->get('notes_pers'));

        if ($titre === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        if (!$this->isValidTime($heureDebut)) {
            $errors[] = 'Heure de début invalide (HH:MM).';
        }

        if ($dureePrevue === null) {
            $errors[] = 'La durée prévue est invalide.';
        }

        if ($typeActivite === '') {
            $errors[] = 'Le type d’activité est obligatoire.';
        }

        if ($couleur !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $couleur)) {
            $errors[] = 'La couleur doit être au format hexadécimal (#RRGGBB).';
        }

        if (empty($errors) && $couleur === '') {
            $couleur = $planningRepo->findColorForType($typeActivite) ?? '#dfe6e9';
        }

        if (empty($errors) && $selectedDate) {
            $overlapMessage = $this->findOverlapMessage(
                $planningRepo,
                $selectedDate,
                $heureDebut,
                $dureePrevue
            );
            if ($overlapMessage) {
                $errors[] = $overlapMessage;
            }
        }

        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors], 422);
        }

        $planning = new PlanningEtude();
        $planning->setTitreP($titre);
        $planning->setDateSeance($selectedDate);
        $planning->setHeureDebut(new \DateTime($heureDebut));
        $planning->setDureePrevue($dureePrevue);
        $planning->setTypeActivite($typeActivite);
        $planning->setDescription('');
        $planning->setNotesPers($notes !== '' ? $notes : null);
        $planning->setEtat('to do');
        $planning->setDateCreation(new \DateTime());
        $planning->setCouleurActivite($couleur);

        $em->persist($planning);
        $em->flush();

        return new JsonResponse([
            'activity' => [
                'id' => $planning->getId(),
                'titre' => $planning->getTitreP(),
                'date' => $planning->getDateSeance()?->format('Y-m-d'),
                'heure_debut' => $planning->getHeureDebut()?->format('H:i'),
                'duree_prevue' => $planning->getDureePrevue(),
                'type_activite' => $planning->getTypeActivite(),
                'couleur_activite' => $planning->getCouleurActivite() ?? '#dfe6e9',
                'etat' => $planning->getEtat(),
                'notes_pers' => $planning->getNotesPers(),
            ],
        ]);
    }

    #[Route('/planning/update/{id}', name: 'front_planning_update', methods: ['POST'])]
    public function updateActivity(
        Request $request,
        PlanningEtude $planning,
        EntityManagerInterface $em,
        PlanningEtudeRepository $planningRepo
    ): JsonResponse {
        $errors = [];

        $dateString = $request->request->get('date');
        $selectedDate = $this->parseDateOrNull($dateString) ?? $planning->getDateSeance();
        if (!$selectedDate) {
            $errors[] = 'La date de séance est obligatoire.';
        }

        $titre = trim((string) $request->request->get('titre_p'));
        $heureDebut = (string) $request->request->get('heure_debut');
        $dureePrevue = $this->parseDurationFields(
            $request->request->get('duree_heures'),
            $request->request->get('duree_minutes')
        );
        $typeActivite = trim((string) $request->request->get('type_activite'));
        $couleur = trim((string) $request->request->get('couleur_activite'));
        $notes = trim((string) $request->request->get('notes_pers'));

        if ($titre === '') {
            $errors[] = 'Le titre est obligatoire.';
        }

        if (!$this->isValidTime($heureDebut)) {
            $errors[] = 'Heure de début invalide (HH:MM).';
        }

        if ($dureePrevue === null) {
            $errors[] = 'La durée prévue est invalide.';
        }

        if ($typeActivite === '') {
            $errors[] = 'Le type d’activité est obligatoire.';
        }

        if ($couleur !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $couleur)) {
            $errors[] = 'La couleur doit être au format hexadécimal (#RRGGBB).';
        }

        if (empty($errors) && $selectedDate) {
            $overlapMessage = $this->findOverlapMessage(
                $planningRepo,
                $selectedDate,
                $heureDebut,
                $dureePrevue,
                $planning->getId()
            );
            if ($overlapMessage) {
                $errors[] = $overlapMessage;
            }
        }

        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors], 422);
        }

        $planning->setTitreP($titre);
        $planning->setDateSeance($selectedDate);
        $planning->setHeureDebut(new \DateTime($heureDebut));
        $planning->setDureePrevue($dureePrevue);
        $planning->setTypeActivite($typeActivite);
        if ($couleur !== '') {
            $planning->setCouleurActivite($couleur);
        }
        $planning->setNotesPers($notes !== '' ? $notes : null);
        $planning->setDateModification(new \DateTime());

        $em->flush();

        return new JsonResponse([
            'activity' => [
                'id' => $planning->getId(),
                'titre' => $planning->getTitreP(),
                'date' => $planning->getDateSeance()?->format('Y-m-d'),
                'heure_debut' => $planning->getHeureDebut()?->format('H:i'),
                'duree_prevue' => $planning->getDureePrevue(),
                'type_activite' => $planning->getTypeActivite(),
                'couleur_activite' => $planning->getCouleurActivite() ?? '#dfe6e9',
                'etat' => $planning->getEtat(),
                'notes_pers' => $planning->getNotesPers(),
            ],
        ]);
    }

    #[Route('/planning/move/{id}', name: 'front_planning_move', methods: ['POST'])]
    public function moveActivity(
        Request $request,
        PlanningEtude $planning,
        EntityManagerInterface $em,
        PlanningEtudeRepository $planningRepo
    ): JsonResponse {
        $errors = [];

        $dateString = $request->request->get('date');
        $selectedDate = $this->parseDateOrNull($dateString) ?? $planning->getDateSeance();
        if (!$selectedDate) {
            $errors[] = 'La date de séance est obligatoire.';
        }

        $heureDebut = (string) $request->request->get('heure_debut');
        if (!$this->isValidTime($heureDebut)) {
            $errors[] = 'Heure de début invalide (HH:MM).';
        }

        $duration = $planning->getDureePrevue();
        if (!$duration || $duration <= 0) {
            $errors[] = 'La durée prévue est invalide.';
        }

        if (empty($errors) && $selectedDate && $duration) {
            $overlapMessage = $this->findOverlapMessage(
                $planningRepo,
                $selectedDate,
                $heureDebut,
                $duration,
                $planning->getId()
            );
            if ($overlapMessage) {
                $errors[] = $overlapMessage;
            }
        }

        if (!empty($errors)) {
            return new JsonResponse(['errors' => $errors], 422);
        }

        $planning->setDateSeance($selectedDate);
        $planning->setHeureDebut(new \DateTime($heureDebut));
        $planning->setDateModification(new \DateTime());
        $em->flush();

        return new JsonResponse([
            'activity' => [
                'id' => $planning->getId(),
                'date' => $planning->getDateSeance()?->format('Y-m-d'),
                'heure_debut' => $planning->getHeureDebut()?->format('H:i'),
                'duree_prevue' => $planning->getDureePrevue(),
                'etat' => $planning->getEtat(),
            ],
        ]);
    }

    #[Route('/planning/delete/{id}', name: 'front_planning_delete', methods: ['POST'])]
    public function deleteActivity(
        PlanningEtude $planning,
        EntityManagerInterface $em
    ): JsonResponse {
        $em->remove($planning);
        $em->flush();

        return new JsonResponse(['status' => 'deleted']);
    }

    #[Route('/planning/toggle/{id}', name: 'front_planning_toggle', methods: ['POST'])]
    public function toggleActivity(
        PlanningEtude $planning,
        EntityManagerInterface $em
    ): JsonResponse {
        $planning->setEtat($planning->getEtat() === 'done' ? 'to do' : 'done');
        $planning->setDateModification(new \DateTime());
        $em->flush();

        return new JsonResponse([
            'etat' => $planning->getEtat(),
        ]);
    }

    #[Route('/planning/suggest', name: 'front_planning_suggest', methods: ['GET'])]
    public function suggestActivity(
        Request $request,
        PlanningEtudeRepository $planningRepo
    ): JsonResponse {
        $title = trim((string) $request->query->get('title'));
        if ($title === '') {
            return new JsonResponse(['suggestion' => null]);
        }

        $last = $planningRepo->findLastByTitle($title);
        if (!$last) {
            return new JsonResponse(['suggestion' => null]);
        }

        return new JsonResponse([
            'suggestion' => [
                'heure_debut' => $last->getHeureDebut()?->format('H:i'),
                'duree_prevue' => $last->getDureePrevue(),
            ],
        ]);
    }

    #[Route('/planning/reminders', name: 'front_planning_reminders', methods: ['GET'])]
    public function reminders(
        PlanningEtudeRepository $planningRepo
    ): JsonResponse {
        $now = new \DateTime();
        $today = new \DateTime($now->format('Y-m-d'));
        $activities = $planningRepo->findByDate($today);

        $upcoming = [];
        $inProgress = [];

        foreach ($activities as $activity) {
            if (in_array($activity->getEtat(), ['done', 'skipped'], true)) {
                continue;
            }
            $start = $activity->getHeureDebut();
            $duration = $activity->getDureePrevue();
            if (!$start || !$duration) {
                continue;
            }

            $startDateTime = new \DateTime($today->format('Y-m-d') . ' ' . $start->format('H:i'));
            $endDateTime = (clone $startDateTime)->modify(sprintf('+%d minutes', $duration));
            $minutesUntilStart = (int) floor(($startDateTime->getTimestamp() - $now->getTimestamp()) / 60);
            $minutesRemaining = (int) floor(($endDateTime->getTimestamp() - $now->getTimestamp()) / 60);

            if ($minutesUntilStart === 10) {
                $upcoming[] = [
                    'id' => $activity->getId(),
                    'titre' => $activity->getTitreP(),
                    'message' => sprintf('You should start %s in 10 minutes.', $activity->getTitreP()),
                ];
            }

            if ($minutesRemaining === 30 && $now >= $startDateTime) {
                $durationLabel = $this->formatDurationLabel($duration);
                $inProgress[] = [
                    'id' => $activity->getId(),
                    'titre' => $activity->getTitreP(),
                    'message' => sprintf('You planned %s, only 30 minutes left.', $durationLabel),
                ];
            }
        }

        return new JsonResponse([
            'upcoming' => $upcoming,
            'in_progress' => $inProgress,
        ]);
    }

    private function resolveSelectedDate(?string $dateString): \DateTime
    {
        if ($dateString) {
            try {
                return new \DateTime($dateString);
            } catch (\Exception $e) {
                return new \DateTime();
            }
        }

        return new \DateTime();
    }

    private function parseDateOrNull(?string $dateString): ?\DateTime
    {
        if (!$dateString) {
            return null;
        }

        try {
            return new \DateTime($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function isValidTime(?string $time): bool
    {
        if (!$time || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            return false;
        }

        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59;
    }

    private function parseDurationFields(?string $hoursInput, ?string $minutesInput): ?int
    {
        $hoursInput = $hoursInput !== null ? trim($hoursInput) : '';
        $minutesInput = $minutesInput !== null ? trim($minutesInput) : '';

        if ($hoursInput === '' && $minutesInput === '') {
            return null;
        }

        $hoursInput = $hoursInput === '' ? '0' : $hoursInput;
        $minutesInput = $minutesInput === '' ? '0' : $minutesInput;

        if (!is_numeric($hoursInput) || !is_numeric($minutesInput)) {
            return null;
        }

        $hours = (int) $hoursInput;
        $minutes = (int) $minutesInput;

        if ($hours < 0 || $minutes < 0 || $minutes > 59) {
            return null;
        }

        $total = ($hours * 60) + $minutes;
        if ($total <= 0) {
            return null;
        }

        return $total;
    }

    private function findOverlapMessage(
        PlanningEtudeRepository $planningRepo,
        \DateTime $selectedDate,
        string $startTime,
        int $durationMinutes,
        ?int $excludeId = null
    ): ?string {
        $newStart = $this->timeToMinutes($startTime);
        $newEnd = $newStart + $durationMinutes;

        $existingActivities = $planningRepo->findBy(
            ['date_seance' => $selectedDate],
            ['heure_debut' => 'ASC']
        );

        foreach ($existingActivities as $existing) {
            if ($excludeId && $existing->getId() === $excludeId) {
                continue;
            }

            $existingStart = $existing->getHeureDebut() ? $this->timeToMinutes($existing->getHeureDebut()->format('H:i')) : null;
            $existingDuration = $existing->getDureePrevue() ?? 0;

            if ($existingStart === null || $existingDuration <= 0) {
                continue;
            }

            $existingEnd = $existingStart + $existingDuration;

            if ($newStart < $existingEnd && $existingStart < $newEnd) {
                $titre = $existing->getTitreP() ?: 'une activite';
                return sprintf('You already have "%s" during this time.', $titre);
            }
        }

        return null;
    }

    private function timeToMinutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return ($hours * 60) + $minutes;
    }

    private function formatDurationLabel(int $durationMinutes): string
    {
        $hours = intdiv($durationMinutes, 60);
        $minutes = $durationMinutes % 60;

        if ($minutes === 0) {
            return sprintf('%dh', $hours);
        }

        return sprintf('%dh%02d', $hours, $minutes);
    }
}
