<?php

namespace App\Controller;

use App\Entity\CategorieArticle;
use App\Form\CategorieArticleType;
use App\Repository\CategorieArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/back/categorie-article')]
class CategorieArticleController extends AbstractController
{
    #[Route('/', name: 'app_categorie_article_index', methods: ['GET'])]
    public function index(Request $request, CategorieArticleRepository $repository): Response
    {
        $search = $request->query->get('search', '');
        $sortBy = $request->query->get('sort', 'nomCategorie');
        $order = $request->query->get('order', 'ASC');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $qb = $repository->createQueryBuilder('c');

        // Recherche
        if (!empty($search)) {
            $qb->andWhere('c.nomCategorie LIKE :search OR c.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        // Tri
        $validSorts = ['id', 'nomCategorie', 'createdAt', 'updatedAt'];
        if (in_array($sortBy, $validSorts)) {
            $qb->orderBy('c.' . $sortBy, strtoupper($order) === 'ASC' ? 'ASC' : 'DESC');
        }

        // Pagination
        $total = count($qb->getQuery()->getResult());
        $categories = $qb->setFirstResult(($page - 1) * $limit)
                        ->setMaxResults($limit)
                        ->getQuery()
                        ->getResult();

        $totalPages = ceil($total / $limit);

        return $this->render('back/categorie_article/index.html.twig', [
            'categories' => $categories,
            'search' => $search,
            'sortBy' => $sortBy,
            'order' => $order,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'total' => $total,
        ]);
    }

 #[Route('/new', name: 'app_categorie_article_new', methods: ['GET', 'POST'])]
public function new(Request $request, EntityManagerInterface $entityManager): Response
{
    $categorie = new CategorieArticle();
    $form = $this->createForm(CategorieArticleType::class, $categorie);
    $form->handleRequest($request);

    // ICI : Vérifier si le formulaire est soumis ET VALIDE
    if ($form->isSubmitted() && $form->isValid()) {
        // Persister et sauvegarder
        $entityManager->persist($categorie);
        $entityManager->flush();

        // Message de succès
        $this->addFlash('success', 'La catégorie a été créée avec succès!');
        return $this->redirectToRoute('app_categorie_article_index');
    }

    // Si le formulaire n'est pas valide, il sera réaffiché avec les erreurs
    return $this->render('back/categorie_article/new.html.twig', [
        'categorie' => $categorie,
        'form' => $form,
    ]);
}

   #[Route('/{id}/edit', name: 'app_categorie_article_edit', methods: ['GET', 'POST'])]
public function edit(Request $request, CategorieArticle $categorie, EntityManagerInterface $entityManager): Response
{
    $form = $this->createForm(CategorieArticleType::class, $categorie);
    $form->handleRequest($request);

    if ($form->isSubmitted() && $form->isValid()) {
        $entityManager->flush();

        $this->addFlash('success', 'La catégorie a été modifiée avec succès!');
        return $this->redirectToRoute('app_categorie_article_index');
    }

    return $this->render('back/categorie_article/edit.html.twig', [
        'categorie' => $categorie,
        'form' => $form,
    ]);
}#[Route('/{id}/delete', name: 'app_categorie_article_delete', methods: ['POST'])]
public function delete(Request $request, CategorieArticle $categorie, EntityManagerInterface $entityManager): Response
{
    // Vérifier le token CSRF
    if (!$this->isCsrfTokenValid('delete' . $categorie->getId(), $request->request->get('_token'))) {
        $this->addFlash('error', 'Token CSRF invalide.');
        return $this->redirectToRoute('app_categorie_article_index');
    }

    // Vérifier s'il y a des articles liés
    if ($categorie->getReferenceArticles()->count() > 0) {
        $this->addFlash('error', 'Impossible de supprimer cette catégorie car elle contient des articles!');
        return $this->redirectToRoute('app_categorie_article_index');
    }

    try {
        $entityManager->remove($categorie);
        $entityManager->flush();
        
        $this->addFlash('success', 'La catégorie a été supprimée avec succès!');
    } catch (\Exception $e) {
        $this->addFlash('error', 'Une erreur est survenue lors de la suppression: ' . $e->getMessage());
    }

    return $this->redirectToRoute('app_categorie_article_index');
}
#[Route('/{id}', name: 'app_categorie_article_show', methods: ['GET'])]
public function show(CategorieArticle $categorie): Response
{
    // Récupérer les articles de cette catégorie
    $articles = $categorie->getReferenceArticles();
    
    return $this->render('back/categorie_article/show.html.twig', [
        'categorie' => $categorie,
        'articles' => $articles,
    ]);
}
}