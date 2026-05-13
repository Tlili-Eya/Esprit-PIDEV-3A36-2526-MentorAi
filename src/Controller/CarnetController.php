<?php
// src/Controller/CarnetController.php

namespace App\Controller;

use App\Entity\Carnet;
use App\Entity\PlanningEtude;
use App\Entity\Utilisateur;
use App\Repository\CarnetRepository;
use App\Repository\PlanningEtudeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;

final class CarnetController extends AbstractController
{
    // --------------------------------------------------------------
    // Main page (HTML)
    // --------------------------------------------------------------
    #[Route('/blog', name: 'front_blog', methods: ['GET'])]
    public function blog(Request $request, CarnetRepository $repository): Response
    {
        $mode = $request->query->get('mode', 'list');
        if (!in_array($mode, ['list', 'read', 'edit'], true)) {
            $mode = 'list';
        }

        $selectedNoteId = $request->query->getInt('id');
        $notes = [];
        $selectedNote = null;

        $user = $this->getUser();
        if ($user instanceof Utilisateur) {
            $noteEntities = $repository->createQueryBuilder('n')
                ->where('n.Utilisateurs = :user')
                ->setParameter('user', $user)
                ->orderBy('n.dateModification', 'DESC')
                ->getQuery()
                ->getResult();

            foreach ($noteEntities as $note) {
                $notes[] = [
                    'id' => $note->getId(),
                    'titre' => $note->getTitre(),
                    'date_affichee' => $note->getDateModification()?->format('d/m/Y H:i')
                        ?? $note->getDateCreation()?->format('d/m/Y H:i')
                        ?? '',
                ];
            }

            if ($selectedNoteId > 0) {
                $note = $repository->find($selectedNoteId);
                if ($note && $note->getUtilisateurs() === $user) {
                    $selectedNote = [
                        'id' => $note->getId(),
                        'titre' => $note->getTitre(),
                        'contenu' => $note->getContenu(),
                    ];
                } elseif ($mode === 'read') {
                    $mode = 'list';
                }
            }
        } elseif ($mode !== 'list') {
            $mode = 'list';
        }

        return $this->render('front/blog.html.twig', [
            'mode' => $mode,
            'notes' => $notes,
            'selected_note' => $selectedNote,
            'errors' => [],
        ]);
    }

    // --------------------------------------------------------------
    // API: get filtered notes (search + visibility filter)
    // --------------------------------------------------------------
    #[Route('/blog/api/notes', name: 'front_blog_api_notes', methods: ['GET'])]
    public function apiNotes(Request $request, CarnetRepository $repository): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json([]);
        }

        $search = $request->query->get('search', '');
        $filter = $request->query->get('filter', 'all'); // 'visible', 'hidden', 'all'

        // Base query builder
        $qb = $repository->createQueryBuilder('n')
            ->where('n.Utilisateurs = :user')
            ->setParameter('user', $user)
            ->orderBy('n.dateModification', 'DESC');

        if ($search !== '') {
            $qb->andWhere('n.titre LIKE :search OR n.contenu LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($filter === 'visible') {
            $qb->andWhere('n.visibilite = :vis')->setParameter('vis', 'visible');
        } elseif ($filter === 'hidden') {
            $qb->andWhere('n.visibilite = :vis')->setParameter('vis', 'hidden');
        }

        $notes = $qb->getQuery()->getResult();

        $payload = [];
        foreach ($notes as $note) {
            $payload[] = [
                'id' => $note->getId(),
                'titre' => $note->getTitre(),
                'contenu' => $note->getContenu(),
                'couleur' => $note->getCouleur(),
                'visibilite' => $note->getVisibilite(),
                'date_creation' => $note->getDateCreation()?->format('Y-m-d H:i'),
                'date_modification' => $note->getDateModification()?->format('Y-m-d H:i'),
            ];
        }

        return $this->json($payload);
    }

    // --------------------------------------------------------------
    // API: get all plannings (for the link button)
    // --------------------------------------------------------------
    #[Route('/blog/api/plannings', name: 'front_blog_api_plannings', methods: ['GET'])]
    public function apiPlannings(PlanningEtudeRepository $planningRepo): JsonResponse
    {
        $plannings = $planningRepo->findAll(); // or add user filter if needed
        $data = [];
        foreach ($plannings as $p) {
            $data[] = [
                'id' => $p->getId(),
                'titre' => $p->getTitreP(),
                'date' => $p->getDateSeance()?->format('Y-m-d'),
            ];
        }
        return $this->json($data);
    }

    // --------------------------------------------------------------
    // API: save (create or update) a note
    // --------------------------------------------------------------
    #[Route('/blog/save', name: 'front_blog_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em, CarnetRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->json(['success' => false, 'errors' => ['Utilisateur non connecté.']], 401);
        }

        $id = $request->request->get('id');
        $titre = trim($request->request->get('titre', ''));
        $contenu = trim($request->request->get('contenu', ''));
        $couleur = $request->request->get('couleur', '#FFFFFF');
        $visibilite = $request->request->get('visibilite', 'visible');

        $errors = [];

        // Validation
        if ($titre === '') {
            $errors[] = 'Le titre est obligatoire.';
        } elseif (strlen($titre) > 100) {
            $errors[] = 'Le titre ne peut pas dépasser 100 caractères.';
        }
        if ($contenu === '') {
            $errors[] = 'Le contenu est obligatoire.';
        } elseif (strlen($contenu) < 5) {
            $errors[] = 'Le contenu doit faire au moins 5 caractères.';
        }
        if (!in_array($visibilite, ['visible', 'hidden'])) {
            $errors[] = 'Visibilité invalide.';
        }

        // Unique title check (exclude current note when editing)
        $excludeId = $id ? (int)$id : 0;
        $existing = $repo->createQueryBuilder('n')
            ->where('n.Utilisateurs = :user')
            ->andWhere('n.titre = :titre')
            ->andWhere('n.id != :excludeId')
            ->setParameter('user', $user)
            ->setParameter('titre', $titre)
            ->setParameter('excludeId', $excludeId)
            ->getQuery()
            ->getOneOrNullResult();
        if ($existing) {
            $errors[] = 'Ce titre existe déjà. Choisissez un titre unique.';
        }

        if (!empty($errors)) {
            return $this->json(['success' => false, 'errors' => $errors], 422);
        }

        // Find or create note
        if ($id) {
            $note = $repo->find($id);
            if (!$note || $note->getUtilisateurs() !== $user) {
                return $this->json(['success' => false, 'errors' => ['Note introuvable.']], 404);
            }
        } else {
            $note = new Carnet();
            $note->setDateCreation(new \DateTime());
            $note->setUtilisateurs($user);
        }

        $note->setTitre($titre);
        $note->setContenu($contenu);
        $note->setCouleur($couleur);
        $note->setVisibilite($visibilite);
        $note->setDateModification(new \DateTime());

        $em->persist($note);
        $em->flush();

        return $this->json(['success' => true, 'id' => $note->getId()]);
    }

    #[Route('/blog/upload-audio', name: 'front_blog_upload_audio', methods: ['POST'])]
    public function uploadAudio(Request $request): JsonResponse
    {
        $file = $request->files->get('audio');
        if (!$file) {
            return $this->json(['error' => 'Fichier audio manquant.'], 422);
        }

        $mimeType = $file->getMimeType() ?? 'audio/webm';
        $ext = pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION) ?: 'webm';

        $projectDir = $this->getParameter('kernel.project_dir');
        $uploadDir = $projectDir . '/public/uploads/carnet';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $safeName = uniqid('carnet_audio_', true) . '.' . $ext;
        $file->move($uploadDir, $safeName);

        return $this->json([
            'url' => '/uploads/carnet/' . $safeName,
            'mime' => $mimeType,
        ]);
    }

    // --------------------------------------------------------------
    // API: delete a note
    // --------------------------------------------------------------
    #[Route('/blog/delete', name: 'front_blog_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $em, CarnetRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        $id = $request->request->get('id');
        if (!$id) {
            return $this->json(['success' => false, 'error' => 'ID manquant.'], 400);
        }

        $note = $repo->find($id);
        if (!$note || $note->getUtilisateurs() !== $user) {
            return $this->json(['success' => false, 'error' => 'Note introuvable.'], 404);
        }

        $em->remove($note);
        $em->flush();

        return $this->json(['success' => true]);
    }

    // --------------------------------------------------------------
    // API: link a note to a planning
    // --------------------------------------------------------------
    #[Route('/blog/link-planning', name: 'front_blog_link_planning', methods: ['POST'])]
    public function linkPlanning(
        Request $request,
        EntityManagerInterface $em,
        CarnetRepository $carnetRepo,
        PlanningEtudeRepository $planningRepo
    ): JsonResponse {
        $user = $this->getUser();
        $noteId = $request->request->get('noteId');
        $planningId = $request->request->get('planningId');

        if (!$noteId || !$planningId) {
            return $this->json(['success' => false, 'error' => 'Paramètres manquants.'], 400);
        }

        $note = $carnetRepo->find($noteId);
        $planning = $planningRepo->find($planningId);

        if (!$note || $note->getUtilisateurs() !== $user) {
            return $this->json(['success' => false, 'error' => 'Note invalide.'], 404);
        }
        if (!$planning) {
            return $this->json(['success' => false, 'error' => 'Planning invalide.'], 404);
        }

        // Avoid duplicate links
        if (!$note->getPlannings()->contains($planning)) {
            $note->addPlanning($planning);
            $em->flush();
        }

        return $this->json(['success' => true]);
    }

    // --------------------------------------------------------------
    // API: get plannings already linked to a note
    // --------------------------------------------------------------
    #[Route('/blog/api/note-plannings/{id}', name: 'front_blog_note_plannings', methods: ['GET'])]
    public function notePlannings(int $id, CarnetRepository $repo): JsonResponse
    {
        $user = $this->getUser();
        $note = $repo->find($id);
        if (!$note || $note->getUtilisateurs() !== $user) {
            return $this->json([]);
        }

        $data = [];
        foreach ($note->getPlannings() as $p) {
            $data[] = [
                'id' => $p->getId(),
                'titre' => $p->getTitreP(),
                'date' => $p->getDateSeance()?->format('Y-m-d'),
            ];
        }
        return $this->json($data);
    }
}