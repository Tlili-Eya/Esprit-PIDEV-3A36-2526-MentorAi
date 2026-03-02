<?php

namespace App\Controller;

use App\Entity\Projet;
use App\Entity\Ressource;
use App\Form\ProjetType;
use App\Form\RessourceType;
use App\Repository\ProjetRepository;
use App\Repository\RessourceRepository;
use App\Service\GroqService;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProjetController extends AbstractController
{
    /**
     * FRONT OFFICE
     */

    #[Route('/projets', name: 'front_projets')]
    public function index(Request $request, EntityManagerInterface $entityManager, ProjetRepository $projetRepository, RessourceRepository $ressourceRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $projects = $projetRepository->findBy(['utilisateur' => $user]);
        $projectId = $request->query->get('id');
        $activeProject = null;

        if ($projectId && $projectId > 0) {
            $activeProject = $projetRepository->findOneBy(['id' => $projectId, 'utilisateur' => $user]);
        }

        $projectForm = $this->createForm(ProjetType::class, $activeProject ?? new Projet());
        $projectForm->handleRequest($request);

        if ($projectForm->isSubmitted() && $projectForm->isValid()) {
            $projet = $projectForm->getData();
            $projet->setUtilisateur($user);
            
            if (!$projet->getId()) {
                 $projet->setDateCreation(new \DateTime());
            }
             $projet->setDateModification(new \DateTime());

            $entityManager->persist($projet);
            $entityManager->flush();

            $this->addFlash('success', 'Le projet a été ' . ($activeProject && $activeProject->getId() ? 'mis à jour' : 'créé') . ' avec succès.');
            return $this->redirectToRoute('front_projets', ['id' => $projet->getId()]);
        }

        $resourceId = $request->query->get('resource_id');
        $resource = null;
        
        if ($resourceId) {
            $resource = $ressourceRepository->findOneBy(['id' => $resourceId]);
            if ($resource) {
                $resourceProject = $resource->getProjet();
                if (!$activeProject || !$resourceProject || $resourceProject->getId() !== $activeProject->getId()) {
                    $resource = null;
                }
            }
        }
        
        if (!$resource) {
            $resource = new Ressource();
        }

        $resourceForm = $this->createForm(RessourceType::class, $resource);
        
        if ($activeProject && $activeProject->getId()) {
            $resourceForm->handleRequest($request);

            if ($resourceForm->isSubmitted() && $resourceForm->isValid()) {
                /** @var UploadedFile|null $file */
                $file = $resourceForm->get('fichier')->getData();
                
                if ($file instanceof UploadedFile) {
                    $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = bin2hex(random_bytes(6)) . '-' . $originalFilename;
                    $extension = $file->guessExtension() ?: $file->getClientOriginalExtension();
                    $newFilename = $safeFilename . '.' . $extension;

                    $projectDir = $this->getParameter('kernel.project_dir');
                    if (!is_string($projectDir)) {
                        throw new \RuntimeException('Paramètre kernel.project_dir invalide.');
                    }

                    try {
                        $file->move(
                            $projectDir . '/public/uploads/ressources',
                            $newFilename
                        );
                        $resource->setUrlRessource('/uploads/ressources/' . $newFilename);
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Erreur lors de l\'upload du fichier.');
                    }
                }

                $resource->setProjet($activeProject);
                if (!$resource->getId()) {
                    $resource->setDateCreation(new \DateTime());
                }
                $resource->setDateModification(new \DateTime());
                
                $entityManager->persist($resource);
                $entityManager->flush();

                $this->addFlash('success', 'Ressource enregistrée avec succès.');
                return $this->redirectToRoute('front_projets', ['id' => $activeProject->getId()]);
            }
        }

        return $this->render('front/projets.html.twig', [
            'projects' => $projects,
            'activeProject' => $activeProject,
            'projectForm' => $projectForm->createView(),
            'resourceForm' => $resourceForm->createView(),
        ]);
    }

    #[Route('/projets/assistant-message', name: 'front_projets_ai', methods: ['POST'])]
    public function assistantMessage(Request $request, GroqService $groq): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'error' => 'Authentification requise'], 401);
        }

        $payload = json_decode($request->getContent(), true);
        $message = is_array($payload) ? trim((string) ($payload['message'] ?? '')) : '';
        $history = is_array($payload['history'] ?? null) ? $payload['history'] : [];
        $systemPrompt = is_array($payload) ? trim((string) ($payload['systemPrompt'] ?? '')) : '';

        if ($message === '') {
            return $this->json(['success' => false, 'error' => 'Message vide'], 400);
        }

        if ($systemPrompt !== '') {
            $message = "Consignes système à respecter strictement :\n" . $systemPrompt . "\n\nMessage utilisateur :\n" . $message;
        }

        $result = $groq->sendMessage($message, $history, 'ROLE_USER');

        if (($result['success'] ?? false) !== true) {
            return $this->json($result, 500);
        }

        return $this->json($result);
    }

    #[Route('/projets/delete/{id}', name: 'front_delete_project')]
    public function deleteProject(Projet $projet, EntityManagerInterface $entityManager): Response
    {
        if ($projet->getUtilisateur() !== $this->getUser()) {
             throw $this->createAccessDeniedException();
        }
        
        $entityManager->remove($projet);
        $entityManager->flush();
        
        return $this->redirectToRoute('front_mes_projets');
    }

    #[Route('/projets/ressource/delete/{id}', name: 'front_delete_ressource')]
    public function deleteRessource(Ressource $ressource, EntityManagerInterface $entityManager): Response
    {
         $project = $ressource->getProjet();
         if (!$project || $project->getUtilisateur() !== $this->getUser()) {
             throw $this->createAccessDeniedException();
         }

         $projectId = $project->getId();
         $entityManager->remove($ressource);
         $entityManager->flush();

         if ($projectId === null) {
             return $this->redirectToRoute('front_mes_projets');
         }

         return $this->redirectToRoute('front_projets', ['id' => $projectId]);
    }

    #[Route('/mes-projets', name: 'front_mes_projets')]
    public function mesProjets(ProjetRepository $projetRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $projects = $projetRepository->findBy(['utilisateur' => $user]);

        return $this->render('front/mesprojets.html.twig', [
            'projects' => $projects,
        ]);
    }

    /**
     * BACK OFFICE (MOVED)
     */

    #[Route('/admin/projets', name: 'back_projets')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminProjets(Request $request, ProjetRepository $projetRepository, EntityManagerInterface $entityManager): Response
    {
        $limit = 3;
        $page = max(1, $request->query->getInt('page', 1));
        $search = $request->query->get('q');
        $sortBy = $request->query->get('sort');

        $qb = $projetRepository->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')
            ->addSelect('u');

        if ($search) {
            $qb->andWhere('p.titre LIKE :search OR p.type LIKE :search OR u.nom LIKE :search OR u.prenom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        switch ($sortBy) {
            case 'titre':
                $qb->orderBy('p.titre', 'ASC');
                break;
            case 'utilisateur':
                $qb->orderBy('u.nom', 'ASC');
                break;
            case 'nb_ressources':
                $qb->leftJoin('p.ressources', 'r')
                   ->groupBy('p.id')
                   ->orderBy('COUNT(r.id)', 'DESC');
                break;
            default:
                $qb->orderBy('p.id', 'ASC');
                break;
        }

        $query = $qb->getQuery();
        $paginator = new \Doctrine\ORM\Tools\Pagination\Paginator($query);
        $paginator->getQuery()
            ->setFirstResult(($limit * ($page - 1)))
            ->setMaxResults($limit);

        $totalProjets = count($paginator);
        $totalPages = (int) ceil($totalProjets / $limit);

        $topUsers = $entityManager->createQuery('
            SELECT u.prenom, u.nom, COUNT(p.id) as totalProjects 
            FROM App\Entity\Projet p 
            JOIN p.utilisateur u 
            GROUP BY u.id 
            ORDER BY totalProjects DESC
        ')->setMaxResults(5)->getResult();

        $topProjectsByResources = $entityManager->createQuery('
            SELECT p.titre, COUNT(r.id) as totalResources 
            FROM App\Entity\Projet p 
            LEFT JOIN p.ressources r 
            GROUP BY p.id 
            ORDER BY totalResources DESC
        ')->setMaxResults(5)->getResult();

        $projectsByType = $entityManager->createQuery('
            SELECT p.type, COUNT(p.id) as count 
            FROM App\Entity\Projet p 
            GROUP BY p.type
        ')->getResult();

        $stats = [
            'total_projects' => $projetRepository->count([]),
            'total_resources' => $entityManager->createQuery('SELECT COUNT(r.id) FROM App\Entity\Ressource r')->getSingleScalarResult(),
            'total_users_with_projects' => $entityManager->createQuery('SELECT COUNT(DISTINCT u.id) FROM App\Entity\Projet p JOIN p.utilisateur u')->getSingleScalarResult(),
        ];

        return $this->render('back/projets.html.twig', [
            'projets' => $paginator,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
            'sortBy' => $sortBy,
            'topUsers' => $topUsers,
            'topProjects' => $topProjectsByResources,
            'projectsByType' => $projectsByType,
            'globalStats' => $stats
        ]);
    }

    #[Route('/admin/projets/pdf', name: 'back_export_pdf_projets')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminExportPdf(ProjetRepository $projetRepository): Response
    {
        $projets = $projetRepository->findAll();
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $dompdf = new Dompdf($pdfOptions);

        $html = $this->renderView('back/pdf_projets.html.twig', [
            'projets' => $projets,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="liste_projets.pdf"',
        ]);
    }

    #[Route('/admin/projets/delete/{id}', name: 'back_delete_projet')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDeleteProjet(Projet $projet, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($projet);
        $entityManager->flush();
        $this->addFlash('success', 'Projet supprimé avec succès.');
        return $this->redirectToRoute('back_projets');
    }

    #[Route('/admin/projets/ressource/delete/{id}', name: 'back_delete_ressource')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminDeleteRessource(Ressource $ressource, EntityManagerInterface $entityManager): Response
    {
        $entityManager->remove($ressource);
        $entityManager->flush();
        $this->addFlash('success', 'Ressource supprimée avec succès.');
        return $this->redirectToRoute('back_projets');
    }

    #[Route('/admin/projets/show/{id}', name: 'back_show_projet')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminShowProjet(Projet $projet): Response
    {
        return $this->render('back/show_projet.html.twig', [
            'projet' => $projet,
        ]);
    }

    #[Route('/admin/projets/edit/{id}', name: 'back_edit_projet')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminEditProjet(Request $request, Projet $projet, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProjetType::class, $projet);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Projet modifié avec succès.');
            return $this->redirectToRoute('back_projets');
        }

        return $this->render('back/edit_projet.html.twig', [
            'projet' => $projet,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/admin/projets/ressource/edit/{id}', name: 'back_edit_ressource')]
    #[IsGranted('ROLE_ADMIN')]
    public function adminEditRessource(Request $request, Ressource $ressource, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(RessourceType::class, $ressource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Ressource modifiée avec succès.');
            return $this->redirectToRoute('back_projets');
        }

        return $this->render('back/edit_ressource.html.twig', [
            'ressource' => $ressource,
            'form' => $form->createView(),
        ]);
    }
}
