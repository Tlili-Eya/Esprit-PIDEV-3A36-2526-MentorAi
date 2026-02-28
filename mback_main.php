<?php
// src/Controller/BackController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\ReferenceArticleRepository;
use App\Repository\PlanActionsRepository;
use App\Repository\SortieAIRepository;
use App\Repository\CategorieArticleRepository;

#[Route('/back')]
class mBackController extends AbstractController
{
    #[Route('/', name: 'back_home')]
    public function dashboard(
        ReferenceArticleRepository $articleRepo,
        PlanActionsRepository $planRepo,
        SortieAIRepository $sortieRepo,
        CategorieArticleRepository $categorieRepo
    ): Response {
        // Statistiques pour l'admin
        $stats = [
            'total_articles' => $articleRepo->count([]),
            'total_plans' => $planRepo->count([]),
            'total_sorties' => $sortieRepo->count([]),
            'total_categories' => $categorieRepo->count([]),
            'articles_publies' => $articleRepo->count(['published' => true]),
            'articles_non_publies' => $articleRepo->count(['published' => false]),
        ];

        // Stats par statut pour les plans
        $plans_par_statut = [
            'en_attente' => $planRepo->count(['statut' => \App\Enum\Statut::EnAttente]),
            'en_cours' => $planRepo->count(['statut' => \App\Enum\Statut::EnCours]),
            'fini' => $planRepo->count(['statut' => \App\Enum\Statut::Fini]),
            'rejete' => $planRepo->count(['statut' => \App\Enum\Statut::Rejete]),
        ];

        // R├®cents
        $derniers_articles = $articleRepo->findBy([], ['date' => 'DESC'], 5);
        $derniers_plans = $planRepo->findBy([], ['date' => 'DESC'], 5);
        $dernieres_sorties = $sortieRepo->findBy([], ['date' => 'DESC'], 5);

        return $this->render('back/dashboard.html.twig', [
            'stats' => $stats,
            'plans_par_statut' => $plans_par_statut,
            'derniers_articles' => $derniers_articles,
            'derniers_plans' => $derniers_plans,
            'dernieres_sorties' => $dernieres_sorties,
        ]);
    }
}
