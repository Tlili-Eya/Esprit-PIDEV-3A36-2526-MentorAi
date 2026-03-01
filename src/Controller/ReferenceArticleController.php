<?php

namespace App\Controller;

use App\Entity\ReferenceArticle;
use App\Form\ReferenceArticleType;
use App\Repository\ReferenceArticleRepository;
use App\Repository\CategorieArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\PdfExportService;

#[Route('/back/reference-article')]
class ReferenceArticleController extends AbstractController
{
    #[Route('/', name: 'app_reference_article_index', methods: ['GET'])]
    public function index(
        Request $request, 
        ReferenceArticleRepository $repository,
        CategorieArticleRepository $categorieRepository
    ): Response
    {
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort', 'createdAt');
        $order = $request->query->get('order', 'DESC');
        $categorie = $request->query->get('categorie', '');
        $published = $request->query->get('published', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $qb = $repository->createQueryBuilder('r')
                        ->leftJoin('r.categorie', 'c')
                        ->addSelect('c');

        // Recherche
        if (!empty($search)) {
            $qb->andWhere('r.titre LIKE :search OR r.contenu LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Filtre par catégorie
        if (!empty($categorie)) {
            $qb->andWhere('c.id = :categorie')
               ->setParameter('categorie', $categorie);
        }

        // Filtre par statut de publication
        if ($published !== '') {
            $qb->andWhere('r.published = :published')
               ->setParameter('published', (bool)$published);
        }

        // Tri
        $validSorts = ['id', 'titre', 'createdAt', 'updatedAt'];
        if (in_array($sortBy, $validSorts)) {
            $qb->orderBy('r.' . $sortBy, strtoupper($order) === 'ASC' ? 'ASC' : 'DESC');
        }

        // Pagination
        $total = count($qb->getQuery()->getResult());
        $articles = $qb->setFirstResult(($page - 1) * $limit)
                      ->setMaxResults($limit)
                      ->getQuery()
                      ->getResult();

        $totalPages = ceil($total / $limit);
        
        // Récupérer toutes les catégories pour le filtre
        $categories = $categorieRepository->findAll();

        return $this->render('back/reference_article/index.html.twig', [
            'articles' => $articles,
            'categories' => $categories,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
            'categorie' => $categorie,
            'published' => $published,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'app_reference_article_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $article = new ReferenceArticle();
        $form = $this->createForm(ReferenceArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Assigner l'admin connecté
            $user = $this->getUser();
            if ($user) {
                $article->setAuteur($user);
            }
            
            $article->setCreatedAt(new \DateTimeImmutable());
            
            $entityManager->persist($article);
            $entityManager->flush();

            $this->addFlash('success', 'L\'article a été créé avec succès!');
            return $this->redirectToRoute('app_reference_article_index');
        }

        return $this->render('back/reference_article/new.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_reference_article_show', methods: ['GET'])]
    public function show(?ReferenceArticle $referenceArticle): Response
    {
        if (!$referenceArticle) {
            throw $this->createNotFoundException('Article non trouvé');
        }
        
        return $this->render('back/reference_article/show.html.twig', [
            'reference_article' => $referenceArticle,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_reference_article_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ReferenceArticle $article, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ReferenceArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->flush();

            $this->addFlash('success', 'L\'article a été modifié avec succès!');
            return $this->redirectToRoute('app_reference_article_index');
        }

        return $this->render('back/reference_article/edit.html.twig', [
            'article' => $article,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_reference_article_delete', methods: ['POST'])]
    public function delete(Request $request, ReferenceArticle $article, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $article->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_reference_article_index');
        }

        try {
            $entityManager->remove($article);
            $entityManager->flush();

            $this->addFlash('success', 'L\'article a été supprimé avec succès!');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_reference_article_index');
    }

    #[Route('/{id}/toggle-publish', name: 'app_reference_article_toggle_publish', methods: ['POST'])]
    public function togglePublish(
        Request $request,
        ReferenceArticle $article,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$this->isCsrfTokenValid('publish' . $article->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_reference_article_index');
        }

        try {
            $article->setPublished(!$article->isPublished());
            $article->setUpdatedAt(new \DateTimeImmutable());
            
            $entityManager->flush();
            
            $status = $article->isPublished() ? 'publié' : 'dépublié';
            $this->addFlash('success', "L'article a été {$status} avec succès!");
        } catch (\Exception $e) {
            $this->addFlash('error', 'Erreur lors de la modification du statut.');
        }

        return $this->redirectToRoute('app_reference_article_index');
    }
    
    #[Route('/{id}/pdf', name: 'app_reference_article_pdf', methods: ['GET'])]
    public function exportPdf(
        ReferenceArticle $article,
        PdfExportService $pdfService
    ): Response {
        return $pdfService->generateArticlePdf($article);
    }
}