<?php

namespace App\Controller;

use App\Entity\SortieAI;
use App\Form\SortieAIType;
use App\Repository\SortieAIRepository;
use App\Service\ExcelExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/back/sortie-ai')]
class SortieAIController extends AbstractController
{
    #[Route('/', name: 'app_sortie_ai_index', methods: ['GET'])]
    public function index(Request $request, SortieAIRepository $repository): Response
    {
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort', 'createdAt');
        $order = $request->query->get('order', 'DESC');
        $cible = $request->query->get('cible', '');
        $typeSortie = $request->query->get('type', '');
        $criticite = $request->query->get('criticite', '');
        $statut = $request->query->get('statut', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        // Construction de la requête
        $qb = $repository->createQueryBuilder('s');

        // Recherche
        if (!empty($search)) {
            $qb->andWhere('s.contenu LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtres
        if (!empty($cible)) {
            $qb->andWhere('s.cible = :cible')
               ->setParameter('cible', $cible);
        }

        if (!empty($typeSortie)) {
            $qb->andWhere('s.typeSortie = :type')
               ->setParameter('type', $typeSortie);
        }

        if (!empty($criticite)) {
            $qb->andWhere('s.criticite = :criticite')
               ->setParameter('criticite', $criticite);
        }

        if (!empty($statut)) {
            $qb->andWhere('s.statut = :statut')
               ->setParameter('statut', $statut);
        }

        // Tri
        $validSorts = ['id', 'createdAt', 'cible', 'typeSortie', 'criticite', 'statut'];
        if (in_array($sortBy, $validSorts)) {
            $qb->orderBy('s.' . $sortBy, strtoupper($order) === 'ASC' ? 'ASC' : 'DESC');
        }

        // Pagination
        $total = count($qb->getQuery()->getResult());
        $sorties = $qb->setFirstResult(($page - 1) * $limit)
                     ->setMaxResults($limit)
                     ->getQuery()
                     ->getResult();

        $totalPages = ceil($total / $limit);

        return $this->render('back/sortie_ai/index.html.twig', [
            'sorties' => $sorties,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
            'cible' => $cible,
            'typeSortie' => $typeSortie,
            'criticite' => $criticite,
            'statut' => $statut,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'app_sortie_ai_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $sortieAI = new SortieAI();
        $form = $this->createForm(SortieAIType::class, $sortieAI);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sortieAI->setCreatedAt(new \DateTime());
            
            $entityManager->persist($sortieAI);
            $entityManager->flush();

            $this->addFlash('success', 'La sortie IA a été créée avec succès!');
            return $this->redirectToRoute('app_sortie_ai_index');
        }

        return $this->render('back/sortie_ai/new.html.twig', [
            'sortie_ai' => $sortieAI,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_sortie_ai_show', methods: ['GET'])]
    public function show(SortieAI $sortieAI): Response
    {
        $content = $sortieAI->getContenu();
        $jsonParsed = null;
        $cleanedText = $content;

        // Extraction du bloc JSON si présent
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $jsonStr = $matches[1];
            $jsonParsed = json_decode($jsonStr, true);
            // On nettoie le texte pour ne pas réafficher le JSON brut
            $cleanedText = trim(str_replace($matches[0], '', $content));
        }

        return $this->render('back/sortie_ai/show.html.twig', [
            'sortie_ai' => $sortieAI,
            'data' => $jsonParsed,
            'text_intro' => $cleanedText
        ]);
    }

    #[Route('/{id}/edit', name: 'app_sortie_ai_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, SortieAI $sortieAI, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(SortieAIType::class, $sortieAI);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $sortieAI->setUpdatedAt(new \DateTime());
            
            $entityManager->flush();

            $this->addFlash('success', 'La sortie IA a été modifiée avec succès!');
            return $this->redirectToRoute('app_sortie_ai_index');
        }

        return $this->render('back/sortie_ai/edit.html.twig', [
            'sortie_ai' => $sortieAI,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_sortie_ai_delete', methods: ['POST'])]
    public function delete(Request $request, SortieAI $sortieAI, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $sortieAI->getId(), $request->request->get('_token'))) {
            $entityManager->remove($sortieAI);
            $entityManager->flush();

            $this->addFlash('success', 'La sortie IA a été supprimée avec succès!');
        }

        return $this->redirectToRoute('app_sortie_ai_index');
    }

    #[Route('/{id}/ignore', name: 'app_sortie_ai_ignore', methods: ['POST'])]
    public function ignore(SortieAI $sortieAI, EntityManagerInterface $entityManager): Response
    {
        $sortieAI->setStatut(\App\Enum\StatutSortie::Ignore);
        $entityManager->flush();

        $this->addFlash('info', 'La sortie IA a été marquée comme ignorée.');
        return $this->redirectToRoute('app_sortie_ai_index');
    }

    #[Route('/stats/dashboard', name: 'app_sortie_ai_stats', methods: ['GET'])]
    public function stats(SortieAIRepository $repository): Response
    {
        $stats = [
            'total' => $repository->count([]),
            'par_cible' => [],
            'par_type' => [],
            'par_criticite' => [],
        ];

        // Stats par cible
        foreach (\App\Enum\Cible::cases() as $cible) {
            $stats['par_cible'][$cible->value] = $repository->count(['cible' => $cible]);
        }

        // Stats par type
        foreach (\App\Enum\TypeSortie::cases() as $type) {
            $stats['par_type'][$type->value] = $repository->count(['typeSortie' => $type]);
        }

        // Stats par criticité
        foreach (\App\Enum\Criticite::cases() as $criticite) {
            $stats['par_criticite'][$criticite->value] = $repository->count(['criticite' => $criticite]);
        }

        return $this->render('back/sortie_ai/stats.html.twig', [
            'stats' => $stats,
        ]);
    }
    #[Route('/export', name: 'app_sortie_ai_export', methods: ['GET'])]
public function export(
    Request $request,
    SortieAIRepository $repository,
    ExcelExportService $excelExport
): Response {
    $sorties = $repository->findAll();
    
    $data = [];
    foreach ($sorties as $sortie) {
        $data[] = [
            $sortie->getId(),
            $sortie->getCible()->value,
            $sortie->getTypeSortie()->value,
            $sortie->getCriticite()->value,
            $sortie->getContenu(),
            $sortie->getCreatedAt()->format('d/m/Y H:i'),
        ];
    }
    
    $headers = ['ID', 'Cible', 'Type', 'Criticité', 'Contenu', 'Date création'];
    
    return $excelExport->exportToExcel($data, 'sorties_ia', $headers);
}
}