<?php

namespace App\Controller;

use App\Repository\PlanActionsRepository;
use App\Repository\ReferenceArticleRepository;
use App\Repository\CategorieArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Feedback;
use App\Entity\ReferenceArticle;
use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Annotation\Route;


#[Route('/enseignant')]
#[IsGranted('ROLE_ENSEIGNANT')]
class EnseignantFrontController extends AbstractController
{
    #[Route('', name: 'app_enseignant_dashboard')]
    public function dashboard(EntityManagerInterface $em, PlanActionsRepository $planRepository, \App\Repository\SortieAIRepository $aiRepository): Response
    {
        $conn = $em->getConnection();
        $user = $this->getUser();
        
        // --- PROPOSITION 3 : LE RADAR ÉTUDIANT ---
        $radarStudents = $conn->executeQuery("
            SELECT u.id, u.nom, u.prenom, u.pdp_url, 
                   (SELECT COUNT(*) FROM sortie_ai s WHERE s.etudiant_id = u.id AND s.type_sortie = 'ALERTE' AND s.statut = 'NOUVEAU') as alerts_count,
                   (SELECT AVG(h.valeur_humeur) 
                    FROM humeur h 
                    JOIN profil_apprentissage pa ON h.profil_apprentissage_id = pa.id 
                    WHERE pa.utilisateur_id = u.id) as avg_mood
            FROM utilisateur u 
            WHERE u.role = 'ETUDIANT'
            ORDER BY alerts_count DESC, avg_mood ASC
            LIMIT 5
        ")->fetchAllAssociative();

        // Statistiques
        $stats = [
            'total_articles' => (int) $conn->executeQuery("SELECT COUNT(*) FROM reference_article WHERE published = 1")->fetchOne(),
            'alertes_ia' => $aiRepository->count(['typeSortie' => \App\Enum\TypeSortie::Alerte, 'statut' => \App\Enum\StatutSortie::Nouveau, 'cible' => \App\Enum\Cible::Enseignant]),
            'plans_pedagogiques' => (int) $conn->executeQuery(
                "SELECT COUNT(DISTINCT p.id) FROM plan_actions p 
                 WHERE p.categorie = 'PEDAGOGIQUE' AND (p.auteur_id = :userId OR p.auteur_id IS NULL)",
                ['userId' => $user->getId()]
            )->fetchOne(),
            'plans_administratifs' => (int) $conn->executeQuery(
                "SELECT COUNT(DISTINCT p.id) FROM plan_actions p 
                 WHERE p.categorie = 'ADMINISTRATIVE' AND (p.auteur_id = :userId OR p.auteur_id IS NULL)",
                ['userId' => $user->getId()]
            )->fetchOne(),
        ];

        // --- PROPOSITION 2 : PLANS EN ATTENTE DE FEEDBACK (ADMIN -> ENSEIGNANT) ---
        // Plans créés par l'admin (auteur_id IS NULL dans nos fixtures) qui n'ont pas encore de feedback
        $pendingFeedbackPlans = $planRepository->createQueryBuilder('p')
            ->leftJoin('p.sortieAI', 's')
            ->where('p.auteur IS NULL OR p.auteur != :user')
            ->andWhere('p.feedbackEnseignant IS NULL')
            ->setParameter('user', $user)
            ->orderBy('p.date', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();
        
        // Plans récents : ceux créés par l'enseignant OU ceux où il a donné son expertise
        $recentPlans = $planRepository->createQueryBuilder('p')
            ->where('p.auteur = :user OR p.feedbackAuteur = :user')
            ->setParameter('user', $user)
            ->orderBy('p.date', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        return $this->render('front/enseignant/dashboard.html.twig', [
            'stats' => $stats,
            'recentPlans' => $recentPlans,
            'pendingFeedbackPlans' => $pendingFeedbackPlans,
            'radarStudents' => $radarStudents
        ]);
    }

    #[Route('/articles', name: 'app_enseignant_articles', methods: ['GET'])]
    public function articles(
        Request $request,
        ReferenceArticleRepository $articleRepository,
        CategorieArticleRepository $categorieRepository
    ): Response
    {
        // Version SÉCURISÉE sans instanciation directe de controller
        try {
            // Récupérer les articles avec pagination simple
            $page = max(1, $request->query->getInt('page', 1));
            $limit = 12;
            
            // Recherche
            $search = $request->query->get('search', '');
            $categorie = $request->query->get('categorie', null);
            
            $qb = $articleRepository->createQueryBuilder('a')
                ->orderBy('a.createdAt', 'DESC');
            
            if (!empty($search)) {
                $qb->andWhere('a.titre LIKE :search OR a.contenu LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }
            
            if ($categorie) {
                $qb->andWhere('a.categorie = :categorie')
                   ->setParameter('categorie', $categorie);
            }
            
            // Pagination
            $total = count($qb->getQuery()->getResult());
            $articles = $qb->setFirstResult(($page - 1) * $limit)
                          ->setMaxResults($limit)
                          ->getQuery()
                          ->getResult();
            
            $totalPages = ceil($total / $limit);
            $categories = $categorieRepository->findAll();
            
            return $this->render('front/enseignant/articles.html.twig', [
                'articles' => $articles,
                'categories' => $categories,
                'search' => $search,
                'categorie' => $categorie,
                'currentPage' => $page,
                'totalPages' => $totalPages,
                'total' => $total,
            ]);
            
        } catch (\Exception $e) {
            // En cas d'erreur, afficher une liste vide
            return $this->render('front/enseignant/articles.html.twig', [
                'articles' => [],
                'categories' => [],
                'search' => '',
                'categorie' => null,
                'currentPage' => 1,
                'totalPages' => 1,
                'total' => 0,
            ]);
        }
    }

    #[Route('/article/{id}', name: 'app_article_detail', methods: ['GET'])]
public function detail(
    int $id,
    ReferenceArticleRepository $repository,
    EntityManagerInterface $em
): Response
{
    try {
        // Récupérer l'article avec sa catégorie
        $article = $repository->createQueryBuilder('a')
            ->leftJoin('a.categorie', 'c')
            ->addSelect('c')
            ->where('a.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$article) {
            // Journaliser l'erreur
            error_log("Article ID {$id} non trouvé dans la base de données");
            
            // Ajouter un message flash
            $this->addFlash('error', "L'article demandé (ID: {$id}) n'existe pas.");
            
            // Rediriger vers la liste des articles
            return $this->redirectToRoute('app_enseignant_articles');
        }

        // Vérifier la publication
        if (!$article->isPublished() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', "Cet article n'est pas disponible.");
            return $this->redirectToRoute('app_enseignant_articles');
        }

        // Récupérer les articles récents pour la sidebar
        $recentArticles = $repository->createQueryBuilder('a')
            ->where('a.published = true')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        // Récupérer l'article précédent et suivant
        $previousArticle = $repository->createQueryBuilder('a')
            ->where('a.id < :id')
            ->andWhere('a.published = true')
            ->setParameter('id', $id)
            ->orderBy('a.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $nextArticle = $repository->createQueryBuilder('a')
            ->where('a.id > :id')
            ->andWhere('a.published = true')
            ->setParameter('id', $id)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        // Créer un tableau simple pour le template
        $simpleArticle = [
            'id' => $article->getId(),
            'titre' => $article->getTitre(),
            'contenu' => $article->getContenu(),
            'createdAt' => $article->getCreatedAt(),
            'updatedAt' => $article->getUpdatedAt(),
            'published' => $article->isPublished(),
            'categorie' => $article->getCategorie() ? [
                'id' => $article->getCategorie()->getId(),
                'nomCategorie' => $article->getCategorie()->getNomCategorie(),
            ] : null,
        ];

        return $this->render('front/enseignant/article_detail.html.twig', [
            'article' => $simpleArticle,
            'recentArticles' => $recentArticles,
            'previousArticle' => $previousArticle,
            'nextArticle' => $nextArticle,
        ]);
        
    } catch (\Exception $e) {
        error_log('Erreur chargement article: ' . $e->getMessage());
        $this->addFlash('error', 'Erreur lors du chargement de l\'article.');
        return $this->redirectToRoute('app_enseignant_articles');
    }
}
    #[Route('/plans', name: 'app_enseignant_plans', methods: ['GET'])]
public function plans(
    Request $request,
    EntityManagerInterface $em
): Response
{
    $startTime = microtime(true);
    
    $search = $request->query->get('search', '');
    $categorie = $request->query->get('categorie', '');
    $page = max(1, $request->query->getInt('page', 1));
    $limit = 9;

    $conn = $em->getConnection();
    
    // Requête principale pour les plans
    $sql = "
        SELECT 
            p.id,
            p.decision,
            p.description,
            p.statut,
            p.date as createdAt,
            p.updated_at as updatedAt,
            s.id as sortie_id,
            s.categorie_sortie as sortie_categorie
        FROM plan_actions p
        LEFT JOIN sortie_ai s ON p.sortie_ai_id = s.id
        WHERE 1=1
    ";
    
    $params = [];
    
    // Conditions
    if (!empty($search)) {
        $sql .= " AND (p.decision LIKE :search OR p.description LIKE :search)";
        $params['search'] = '%' . $search . '%';
    }
    
    if (!empty($categorie) && in_array($categorie, ['PEDAGOGIQUE', 'ADMINISTRATIVE', 'STRATEGIQUE'])) {
        $sql .= " AND s.categorie_sortie = :categorie";
        $params['categorie'] = $categorie;
    }
    
    // Tri par date
    $sql .= " ORDER BY p.updated_at DESC";
    
    // Compter
    $countSql = "SELECT COUNT(*) as total FROM plan_actions p LEFT JOIN sortie_ai s ON p.sortie_ai_id = s.id WHERE 1=1";
    
    $countParams = [];
    if (!empty($search)) {
        $countSql .= " AND (p.decision LIKE :search OR p.description LIKE :search)";
        $countParams['search'] = '%' . $search . '%';
    }
    if (!empty($categorie) && in_array($categorie, ['PEDAGOGIQUE', 'ADMINISTRATIVE', 'STRATEGIQUE'])) {
        $countSql .= " AND s.categorie_sortie = :categorie";
        $countParams['categorie'] = $categorie;
    }
    
    $total = $conn->executeQuery($countSql, $countParams)->fetchOne();
    
    // Pagination
    $offset = ($page - 1) * $limit;
    $sql .= " LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    // Exécuter la requête principale
    $startQuery = microtime(true);
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $plansData = $stmt->executeQuery()->fetchAllAssociative();
    $queryTime = microtime(true) - $startQuery;
    
    error_log("Enseignant plans query: " . sprintf("%.3f", $queryTime) . "s");
    
    // Pour chaque plan, récupérer les articles séparément
    $plans = [];
    foreach ($plansData as $data) {
        // Récupérer les articles pour ce plan
        $articlesSql = "
            SELECT 
                a.id,
                a.titre,
                a.published,
                ac.id as categorie_id,
                ac.nom_categorie as categorie_nom
            FROM plan_actions_articles paa
            JOIN reference_article a ON paa.reference_article_id = a.id
            LEFT JOIN categorie_article ac ON a.categorie_id = ac.id
            WHERE paa.plan_actions_id = :plan_id
            ORDER BY a.titre
        ";
        
        $articlesStmt = $conn->prepare($articlesSql);
        $articlesStmt->bindValue('plan_id', $data['id']);
        $articles = $articlesStmt->executeQuery()->fetchAllAssociative();
        
        $plans[] = [
            'id' => $data['id'],
            'decision' => $data['decision'],
            'description' => $data['description'],
            'statut' => $data['statut'],
            'date' => $data['createdAt'] ? new \DateTime($data['createdAt']) : null,
            'updatedAt' => $data['updatedAt'] ? new \DateTime($data['updatedAt']) : null,
            'sortieAI' => $data['sortie_id'] ? [
                'id' => $data['sortie_id'],
                'categorieSortie' => $data['sortie_categorie'],
            ] : null,
            'articles' => $articles,
        ];
    }
    
    $totalPages = ceil($total / $limit);
    
    $totalTime = microtime(true) - $startTime;
    error_log("Enseignant plans TOTAL: " . sprintf("%.3f", $totalTime) . "s - " . count($plans) . " plans");
    
    return $this->render('front/enseignant/plans.html.twig', [
        'plans' => $plans,
        'search' => $search,
        'categorie' => $categorie,
        'currentPage' => $page,
        'totalPages' => $totalPages,
        'total' => $total,
    ]);
}

    #[Route('/plan/{id}/feedback', name: 'app_enseignant_plan_feedback', methods: ['GET', 'POST'])]
    public function planFeedback(int $id, Request $request, PlanActionsRepository $planRepository, EntityManagerInterface $em): Response
    {
        $plan = $planRepository->find($id);
        if (!$plan) {
            throw $this->createNotFoundException('Plan non trouvé');
        }

        if ($request->isMethod('POST')) {
            $feedback = $request->request->get('feedback');
            $statut = $request->request->get('statut');
            
            error_log("POST Feedback received for plan " . $id . ": " . $feedback);

            if ($feedback) {
                $plan->setFeedbackEnseignant($feedback);
                $plan->setFeedbackAuteur($this->getUser());
                $plan->setFeedbackDate(new \DateTimeImmutable());
                
                if ($statut) {
                    $plan->setStatut(\App\Enum\Statut::from($statut));
                }
                
                $em->persist($plan);
                $em->flush();
                
                $this->addFlash('success', 'Votre expertise terrain a été enregistrée. L\'administration en a été notifiée.');
                return $this->redirectToRoute('app_enseignant_plan_detail', ['id' => $id], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('front/enseignant/plan_feedback.html.twig', [
            'plan' => $plan
        ]);
    }

    #[Route('/treat-alerts/{studentId}', name: 'app_enseignant_treat_alerts', methods: ['POST'])]
    public function treatAlerts(int $studentId, EntityManagerInterface $em): JsonResponse
    {
        $conn = $em->getConnection();
        $conn->executeStatement(
            "UPDATE sortie_ai SET statut = 'TRAITEE' WHERE etudiant_id = ? AND type_sortie = 'ALERTE'",
            [$studentId]
        );
        
        return $this->json(['success' => true]);
    }

    #[Route('/plan/{id}', name: 'app_enseignant_plan_detail', methods: ['GET'])]
public function planDetail(int $id, EntityManagerInterface $em): Response
{
    // Validation
    if ($id <= 0) {
        $this->addFlash('error', 'ID de plan invalide.');
        return $this->redirectToRoute('app_enseignant_plans');
    }
    
    try {
        $conn = $em->getConnection();
        
        // Vérifier existence rapide
        $exists = (bool) $conn->executeQuery(
            "SELECT EXISTS(SELECT 1 FROM plan_actions WHERE id = ?) as exists_flag", 
            [$id]
        )->fetchOne();
        
        if (!$exists) {
            $availableIds = $conn->executeQuery(
                "SELECT id, decision FROM plan_actions ORDER BY updated_at DESC LIMIT 5"
            )->fetchAllAssociative();
            
            return $this->render('front/enseignant/plan_error.html.twig', [
                'id' => $id,
                'error' => 'Le plan demandé n\'a pas été trouvé.',
                'suggestions' => $availableIds,
            ]);
        }
        
        // Charger les données du plan
        $sql = "
            SELECT 
                p.id,
                p.decision,
                p.description,
                p.statut,
            p.date as createdAt,
            p.updated_at as updatedAt,
            p.feedback_enseignant,
            p.feedback_date,
            
            -- Sortie AI
            s.id as sortie_id,
            s.contenu as sortie_contenu,
            s.categorie_sortie as sortie_categorie
            
        FROM plan_actions p
        LEFT JOIN sortie_ai s ON p.sortie_ai_id = s.id
        WHERE p.id = ?
        LIMIT 1
    ";
    
    $planData = $conn->executeQuery($sql, [$id])->fetchAssociative();
    
    if (!$planData) {
        throw new \Exception("Erreur lors du chargement du plan #{$id}");
    }
    
    // Récupérer les articles pour ce plan
    $articlesSql = "
        SELECT 
            a.id,
            a.titre,
            a.contenu,
            a.published,
            a.created_at as createdAt,
            ac.id as categorie_id,
            ac.nom_categorie as categorie_nom
        FROM plan_actions_articles paa
        JOIN reference_article a ON paa.reference_article_id = a.id
        LEFT JOIN categorie_article ac ON a.categorie_id = ac.id
        WHERE paa.plan_actions_id = :plan_id
        ORDER BY a.titre
    ";
    
    $articlesStmt = $conn->prepare($articlesSql);
    $articlesStmt->bindValue('plan_id', $id);
    $articles = $articlesStmt->executeQuery()->fetchAllAssociative();
    
    // Préparer les données
    $plan = [
        'id' => $planData['id'],
        'decision' => $planData['decision'] ?? 'Sans titre',
        'description' => $planData['description'] ?? 'Aucune description',
        'statut' => $planData['statut'] ?? 'INCONNU',
        'date' => $planData['createdAt'] ? new \DateTime($planData['createdAt']) : null,
        'updatedAt' => $planData['updatedAt'] ? new \DateTime($planData['updatedAt']) : null,
        'feedbackEnseignant' => $planData['feedback_enseignant'],
        'feedbackDate' => $planData['feedback_date'] ? new \DateTime($planData['feedback_date']) : null,
        'articles' => $articles,
        
        'sortieAI' => $planData['sortie_id'] ? [
            'id' => $planData['sortie_id'],
            'contenu' => $planData['sortie_contenu'],
            'categorieSortie' => $planData['sortie_categorie'],
        ] : null,
    ];
    
        
        return $this->render('front/enseignant/plan_detail.html.twig', [
            'plan' => $plan,
        ]);
        
    } catch (\Exception $e) {
        error_log("Enseignant Plan Detail ERROR ID {$id}: " . $e->getMessage());
        
        $this->addFlash('error', "Erreur lors du chargement du plan #{$id}.");
        return $this->redirectToRoute('app_enseignant_plans');
    }
}
    #[Route('/article/{id}/reclamer', name: 'app_enseignant_article_reclamer', methods: ['POST'])]
    public function articleReclamation(int $id, Request $request, ReferenceArticleRepository $articleRepository, EntityManagerInterface $em): Response
    {
        $article = $articleRepository->find($id);
        if (!$article) {
            throw $this->createNotFoundException('Article non trouvé');
        }

        $motif = $request->request->get('motif');
        $description = $request->request->get('description');

        if ($motif && $description) {
            $feedback = new Feedback();
            $feedback->setContenu("ARTICLE #$id ({$article->getTitre()}) - MOTIF: $motif - DESCRIPTION: $description");
            $feedback->setNote(0);
            $feedback->setDatefeedback(new \DateTime());
            $feedback->setTypefeedback('RECLAMATION');
            $feedback->setEtatfeedback('NOUVEAU');
            $feedback->setUtilisateur($this->getUser());

            $em->persist($feedback);
            $em->flush();

            $this->addFlash('success', 'Votre réclamation a été transmise à l\'équipe pédagogique.');
        } else {
            $this->addFlash('error', 'Veuillez remplir tous les champs de la réclamation.');
        }

        return $this->redirectToRoute('app_article_detail', ['id' => $id]);
    }

    #[Route('/reclamation/plan/{planId}', name: 'app_enseignant_reclamation_plan', methods: ['GET'])]
    public function reclamationPourPlan(int $planId): Response
    {
        // Fonctionnalité temporairement désactivée
        $this->addFlash('info', 'La fonctionnalité de réclamation pour les plans est temporairement désactivée. Elle sera disponible prochainement.');
        return $this->redirectToRoute('app_enseignant_plan_detail', ['id' => $planId]);
    }

    #[Route('/chat-ia', name: 'app_enseignant_chat_ia', methods: ['GET'])]
    public function chatIA(): Response
    {
        return $this->render('front/enseignant/chat_ia.html.twig');
    }

    #[Route('/statistiques', name: 'app_enseignant_stats', methods: ['GET'])]
    public function statistiques(
        PlanActionsRepository $planRepository,
        ReferenceArticleRepository $articleRepository
    ): Response
    {
        $user = $this->getUser();
        
        $stats = [
            'articles_lus' => 0,
            'plans_consultes' => 0,
            'reclamations_envoyees' => 0,
        ];

        // Pour l'instant, toutes les statistiques sont à 0
        // Vous pourrez implémenter ces fonctionnalités plus tard
        
        return $this->render('front/enseignant/statistiques.html.twig', [
            'stats' => $stats,
            'user' => $user,
        ]);
    }

    #[Route('/reclamations', name: 'app_enseignant_reclamations', methods: ['GET'])]
    public function reclamations(): Response
    {
        // Version temporaire - rediriger vers le dashboard
        $this->addFlash('info', 'La page des réclamations sera disponible prochainement.');
        return $this->redirectToRoute('app_enseignant_dashboard');
    }
    #[Route('/debug/articles', name: 'app_enseignant_debug_articles', methods: ['GET'])]
public function debugArticles(ReferenceArticleRepository $repository): Response
{
    $articles = $repository->findAll();
    
    $html = '<h2>Liste des articles disponibles</h2>';
    $html .= '<table border="1" cellpadding="5">';
    $html .= '<tr><th>ID</th><th>Titre</th><th>Publié</th><th>Catégorie</th></tr>';
    
    foreach ($articles as $article) {
        $html .= '<tr>';
        $html .= '<td>' . $article->getId() . '</td>';
        $html .= '<td>' . $article->getTitre() . '</td>';
        $html .= '<td>' . ($article->isPublished() ? 'Oui' : 'Non') . '</td>';
        $html .= '<td>' . ($article->getCategorie() ? $article->getCategorie()->getNomCategorie() : 'Aucune') . '</td>';
        $html .= '</tr>';
    }
    $html .= '</table>';
    
    return new Response($html);
}
    #[Route('/preferences', name: 'app_enseignant_preferences')]
    public function preferences(): Response
    {
        return $this->render('front/enseignant/preferences.html.twig');
    }
}
