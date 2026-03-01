<?php

namespace App\Controller;

use App\Entity\PlanActions;
use App\Form\PlanActionsType;
use App\Repository\PlanActionsRepository;
use App\Repository\ReferenceArticleRepository; // AJOUTER CET IMPORT
use App\Enum\Statut;
use App\Service\PdfExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/back/plan-actions')]
class PlanActionsController extends AbstractController
{
    #[Route('/', name: 'app_plan_actions_index', methods: ['GET'])]
    public function index(Request $request, PlanActionsRepository $repository): Response
    {
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort', 'date');
        $order = $request->query->get('order', 'DESC');
        $statut = $request->query->get('statut', '');
        $categorie = $request->query->get('categorie', '');
        $dateDebut = $request->query->get('date_debut', '');
        $dateFin = $request->query->get('date_fin', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $qb = $repository->createQueryBuilder('p');

        if (!empty($search)) {
            $qb->andWhere('p.decision LIKE :search OR p.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if (!empty($statut)) {
            try {
                $statutEnum = Statut::from($statut);
                $qb->andWhere('p.statut = :statut')
                   ->setParameter('statut', $statutEnum);
            } catch (\ValueError $e) {}
        }

        if (!empty($categorie)) {
            $qb->andWhere('p.categorie = :categorie')
               ->setParameter('categorie', $categorie);
        }

        if (!empty($dateDebut)) {
            $qb->andWhere('p.date >= :date_debut')
               ->setParameter('date_debut', new \DateTime($dateDebut));
        }

        if (!empty($dateFin)) {
            $qb->andWhere('p.date <= :date_fin')
               ->setParameter('date_fin', new \DateTime($dateFin . ' 23:59:59'));
        }

        $validSorts = ['id', 'decision', 'date', 'statut'];
        if (in_array($sortBy, $validSorts)) {
            $qb->orderBy('p.' . $sortBy, strtoupper($order) === 'ASC' ? 'ASC' : 'DESC');
        } else {
            $qb->orderBy('p.date', 'DESC');
        }

        $total = count($qb->getQuery()->getResult());
        $plans = $qb->setFirstResult(($page - 1) * $limit)
                    ->setMaxResults($limit)
                    ->getQuery()
                    ->getResult();

        $totalPages = ceil($total / $limit);

        return $this->render('back/plan_actions/index.html.twig', [
            'plans' => $plans,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
            'statut' => $statut,
            'categorie' => $categorie,
            'date_debut' => $dateDebut,
            'date_fin' => $dateFin,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'app_plan_actions_new', methods: ['GET', 'POST'])]
public function new(
    Request $request, 
    EntityManagerInterface $entityManager,
    ReferenceArticleRepository $articleRepo
): Response
{
    $planAction = new PlanActions();
    
    // Pré-remplissage via SortieAI si présent en paramètre (Pont Opérationnel)
    if ($sortieId = $request->query->get('from_sortie')) {
        $sortie = $entityManager->getRepository(\App\Entity\SortieAI::class)->find($sortieId);
        if ($sortie) {
            $planAction->setSortieAI($sortie);
            // On pré-remplit les champs avec les données de l'IA
            $planAction->setDecision("Action suite à : " . $sortie->getTypeSortie()->value);
            $planAction->setDescription($sortie->getContenu());
            $planAction->setCategorie($sortie->getCategorieSortie());
        }
    }
    
    $form = $this->createForm(PlanActionsType::class, $planAction);
    $form->handleRequest($request);

    if ($form->isSubmitted()) {
        if ($form->isValid()) {
            try {
                $planAction->setAuteur($this->getUser());
                $entityManager->persist($planAction);
                
                // Si le plan est lié à une sortie IA, on marque la sortie comme PLANIFIEE
                if ($planAction->getSortieAI()) {
                    $planAction->getSortieAI()->setStatut(\App\Enum\StatutSortie::Planifie);
                }

                $entityManager->flush();

                $this->addFlash('success', 'Le plan d\'action a été créé avec succès!');
                return $this->redirectToRoute('app_plan_actions_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('warning', 'Veuillez remplir tous les champs obligatoires.');
        }
    }

    $totalArticles = $articleRepo->count([]);

    return $this->render('back/plan_actions/new.html.twig', [
        'plan_actions' => $planAction,
        'form' => $form,
        'total_articles' => $totalArticles,
    ]);
}

    #[Route('/{id}', name: 'app_plan_actions_show', methods: ['GET'])]
    public function show(PlanActions $planAction): Response
    {
        return $this->render('back/plan_actions/show.html.twig', [
            'plan_actions' => $planAction,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_plan_actions_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PlanActions $planAction, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PlanActionsType::class, $planAction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $entityManager->flush();

                $this->addFlash('success', 'Le plan d\'action a été modifié avec succès!');
                return $this->redirectToRoute('app_plan_actions_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification: ' . $e->getMessage());
            }
        } else {
            if ($form->isSubmitted()) {
                $this->addFlash('warning', 'Veuillez corriger les erreurs dans le formulaire.');
            }
        }

        return $this->render('back/plan_actions/edit.html.twig', [
            'plan_actions' => $planAction,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_plan_actions_delete', methods: ['POST'])]
    public function delete(Request $request, PlanActions $planAction, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $planAction->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_plan_actions_index');
        }

        try {
            $entityManager->remove($planAction);
            $entityManager->flush();

            $this->addFlash('success', 'Le plan d\'action a été supprimé avec succès!');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_plan_actions_index');
    }

  #[Route('/export/pdf', name: 'app_plan_actions_export_pdf', methods: ['GET'])]
public function exportPdf(
    Request $request,
    PlanActionsRepository $repository,
    PdfExportService $pdfService
): Response {
    $search = $request->query->get('search', '');
    $statut = $request->query->get('statut', '');
    $categorie = $request->query->get('categorie', '');
    $dateDebut = $request->query->get('date_debut', '');
    $dateFin = $request->query->get('date_fin', '');

    $qb = $repository->createQueryBuilder('p')
        ->leftJoin('p.articles', 'a')
        ->addSelect('a');

    if (!empty($search)) {
        $qb->andWhere('p.decision LIKE :search OR p.description LIKE :search')
           ->setParameter('search', '%' . $search . '%');
    }

    if (!empty($statut)) {
        try {
            $statutEnum = Statut::from($statut);
            $qb->andWhere('p.statut = :statut')
               ->setParameter('statut', $statutEnum);
        } catch (\ValueError $e) {}
    }

    if (!empty($categorie)) {
        $qb->andWhere('p.categorie = :categorie')
           ->setParameter('categorie', $categorie);
    }

    if (!empty($dateDebut)) {
        $qb->andWhere('p.date >= :date_debut')
           ->setParameter('date_debut', new \DateTime($dateDebut));
    }

    if (!empty($dateFin)) {
        $qb->andWhere('p.date <= :date_fin')
           ->setParameter('date_fin', new \DateTime($dateFin . ' 23:59:59'));
    }

    $qb->orderBy('p.date', 'DESC');
    
    $plans = $qb->getQuery()->getResult();

    // Créer le tableau des filtres
    $filters = [
        'search' => $search,
        'statut' => $statut,
        'categorie' => $categorie,
        'date_debut' => $dateDebut,
        'date_fin' => $dateFin,
    ];

    // Passer les filtres au service
    return $pdfService->generatePlansListPdf($plans, $filters);
}
    // ROUTE DE DÉBOGAGE À AJOUTER TEMPORAIREMENT
    #[Route('/debug/articles', name: 'debug_articles')]
    public function debugArticles(ReferenceArticleRepository $repo): Response
    {
        $articles = $repo->findAll();
        $output = "<h2>Articles dans la base : " . count($articles) . "</h2>";
        $output .= "<ul>";
        foreach ($articles as $article) {
            $categorie = $article->getCategorie() ? $article->getCategorie()->getNomCategorie() : 'Sans catégorie';
            $statut = $article->isPublished() ? 'Publié' : 'Brouillon';
            $output .= "<li><strong>{$article->getTitre()}</strong> - Catégorie: {$categorie} - {$statut}</li>";
        }
        $output .= "</ul>";
        
        return new Response($output);
    }
}