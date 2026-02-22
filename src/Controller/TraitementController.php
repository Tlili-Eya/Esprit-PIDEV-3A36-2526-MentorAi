<?php

namespace App\Controller;

use App\Entity\Feedback;
use App\Entity\Traitement;
use App\Repository\FeedbackRepository;
use App\Repository\TraitementRepository;
use App\Repository\UtilisateurRepository;
use App\Service\AzureEmotionAnalysisService;
use App\Service\EmailNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'back_')]
#[IsGranted('ROLE_ADMIN')]
final class TraitementController extends AbstractController
{
    // ============================================================
    // GESTION DES FEEDBACKS ET TRAITEMENTS
    // ============================================================

    /**
     * Analyse des messages répétitifs (ALERTE)
     */
    private function analyzeRepetitiveMessages(array $feedbacks): array
    {
        $mots = [];
        
        foreach ($feedbacks as $feedback) {
            $contenu = strtolower($feedback->getContenu());
            
            // Découper en mots et compter les occurrences
            $motsContenu = preg_split('/\s+/', $contenu);
            foreach ($motsContenu as $mot) {
                // Nettoyer les ponctuations
                $mot = preg_replace('/[^a-zàâäéèêëïîôùûüç0-9]/u', '', $mot);
                
                // Ignorer les mots trop courts
                if (strlen($mot) < 4) continue;
                
                if (!isset($mots[$mot])) {
                    $mots[$mot] = 0;
                }
                $mots[$mot]++;
            }
        }
        
        // Trier par occurrence décroissante
        arsort($mots);
        
        // Retourner les 10 mots les plus répétés
        return array_slice($mots, 0, 10, true);
    }

    /**
     * PAGE 1 : Liste de tous les feedbacks (en_attente et traités)
     * AVEC TRI, RECHERCHE UTILISATEUR, ANALYSE DES MESSAGES ET ÉMOTIONS
     */
    #[Route('/contact', name: 'contact')]
    public function contact(
        Request $request,
        FeedbackRepository $feedbackRepo,
        UtilisateurRepository $userRepo,
        AzureEmotionAnalysisService $emotionService
    ): Response {
        // RECHERCHE par utilisateur (nom/prénom/email)
        $searchUser = trim($request->query->get('search_user', ''));
        $showOnlyUntreated = $request->query->get('only_untreated', false);
        
        // Récupérer tous les feedbacks en attente
        $feedbacksEnAttente = $feedbackRepo->findByState('en_attente');

        // Récupérer tous les feedbacks traités  
        $feedbacksTraites = $feedbackRepo->findByState('traite');
        
        // FILTRAGE par utilisateur si recherche
        if (!empty($searchUser)) {
            $feedbacksEnAttente = array_filter($feedbacksEnAttente, function($feedback) use ($searchUser) {
                $user = $feedback->getUtilisateur();
                if (!$user) return false;
                
                return stripos($user->getNom(), $searchUser) !== false
                    || stripos($user->getPrenom(), $searchUser) !== false
                    || stripos($user->getEmail(), $searchUser) !== false;
            });
            
            if (!$showOnlyUntreated) {
                $feedbacksTraites = array_filter($feedbacksTraites, function($feedback) use ($searchUser) {
                    $user = $feedback->getUtilisateur();
                    if (!$user) return false;
                    
                    return stripos($user->getNom(), $searchUser) !== false
                        || stripos($user->getPrenom(), $searchUser) !== false
                        || stripos($user->getEmail(), $searchUser) !== false;
                });
            } else {
                // Si "only_untreated" est activé, on vide les feedbacks traités
                $feedbacksTraites = [];
            }
        }
        
        // ANALYSE des messages répétitifs (ALERTE)
        $allFeedbacks = array_merge($feedbacksEnAttente, $feedbacksTraites);
        $motsCles = $this->analyzeRepetitiveMessages($allFeedbacks);

        // ✅ ANALYSE D'ÉMOTIONS AZURE - Limité aux 10 premiers feedbacks en attente
        $emotionAnalysis = [];
        $feedbacksToAnalyze = array_slice($feedbacksEnAttente, 0, 10);
        
        foreach ($feedbacksToAnalyze as $feedback) {
            $emotionResult = $emotionService->analyzeEmotion($feedback->getContenu());
            $emotionAnalysis[$feedback->getId()] = $emotionResult;
        }

        return $this->render('back/contact.html.twig', [
            'feedbacksEnAttente' => $feedbacksEnAttente,
            'feedbacksTraites' => $feedbacksTraites,
            'searchUser' => $searchUser,
            'motsCles' => $motsCles,
            'showOnlyUntreated' => $showOnlyUntreated,
            'emotionAnalysis' => $emotionAnalysis, // ✅ Nouveau : données d'émotions
        ]);
    }

    /**
     * PAGE 2 : Formulaire de traitement d'un feedback
     * ✅ UTILISE LA VALIDATION PHP DES ENTITÉS
     * ✅ ENVOIE UN EMAIL À L'UTILISATEUR
     * ✅ ANALYSE D'ÉMOTIONS AZURE
     */
    #[Route('/traitement/{id}', name: 'traitement', methods: ['GET', 'POST'])]
    public function traitement(
        Request $request,
        Feedback $feedback,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        EmailNotificationService $emailService,
        AzureEmotionAnalysisService $emotionService
    ): Response {
        // Vérifier si le feedback a déjà un traitement
        $traitement = $feedback->getTraitement();

        // Si c'est une requête POST, enregistrer le traitement
        if ($request->isMethod('POST')) {
            $typeTraitement = trim($request->request->get('type_traitement'));
            $decision = trim($request->request->get('decision'));

            // Si pas de traitement existant, en créer un nouveau
            if (!$traitement) {
                $traitement = new Traitement();
                $traitement->setFeedback($feedback);
            }

            // Remplir les données
            $traitement->setTypetraitement($typeTraitement);
            $traitement->setDescription($decision);
            $traitement->setDecision($typeTraitement);
            $traitement->setDatetraitement(new \DateTime());

            // ✅ VALIDATION PHP via les contraintes de l'entité
            $errors = $validator->validate($traitement);
            
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->redirectToRoute('back_traitement', ['id' => $feedback->getId()]);
            }

            // Mettre à jour l'état du feedback
            $feedback->setEtatfeedback('traite');

            // Sauvegarder
            $em->persist($traitement);
            $em->flush();

            // ✅ ENVOYER L'EMAIL DE NOTIFICATION À L'UTILISATEUR
            try {
                $emailService->sendFeedbackTreatedNotification($feedback);
                $this->addFlash('success', 'Traitement enregistré avec succès ! Un email a été envoyé à l\'utilisateur.');
            } catch (\Exception $e) {
                // Si l'email échoue, le traitement est quand même enregistré
                $this->addFlash('success', 'Traitement enregistré avec succès !');
                $this->addFlash('warning', 'Note : L\'email de notification n\'a pas pu être envoyé à l\'utilisateur.');
                
                // Log l'erreur pour debug (optionnel)
                // error_log('Email error: ' . $e->getMessage());
            }

            return $this->redirectToRoute('back_contact');
        }

        // ✅ ANALYSE D'ÉMOTIONS pour ce feedback spécifique
        $emotionAnalysis = $emotionService->analyzeEmotion($feedback->getContenu());

        // Afficher le formulaire
        return $this->render('back/traitement.html.twig', [
            'feedback' => $feedback,
            'traitement' => $traitement,
            'emotionAnalysis' => $emotionAnalysis, // ✅ Données d'émotion pour ce feedback
        ]);
    }

    /**
     * MODIFIER un traitement existant
     * ✅ UTILISE LA VALIDATION PHP DES ENTITÉS
     * ✅ ENVOIE UN EMAIL DE MISE À JOUR À L'UTILISATEUR
     */
    #[Route('/traitement/{id}/edit', name: 'traitement_edit', methods: ['GET', 'POST'])]
    public function editTraitement(
        Request $request,
        Traitement $traitement,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        EmailNotificationService $emailService  // ✅ SERVICE EMAIL
    ): Response {
        $feedback = $traitement->getFeedback();

        // Si c'est une requête POST, mettre à jour
        if ($request->isMethod('POST')) {
            $typeTraitement = trim($request->request->get('type_traitement'));
            $decision = trim($request->request->get('decision'));

            $traitement->setTypetraitement($typeTraitement);
            $traitement->setDescription($decision);
            $traitement->setDecision($typeTraitement);
            $traitement->setDatetraitement(new \DateTime());

            // ✅ VALIDATION PHP via les contraintes de l'entité
            $errors = $validator->validate($traitement);
            
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->redirectToRoute('back_traitement_edit', ['id' => $traitement->getId()]);
            }

            $em->flush();

            // ✅ ENVOYER UN EMAIL DE MISE À JOUR
            try {
                $emailService->sendFeedbackTreatedNotification($feedback);
                $this->addFlash('success', 'Traitement modifié avec succès ! Un email de mise à jour a été envoyé à l\'utilisateur.');
            } catch (\Exception $e) {
                // Si l'email échoue, la modification est quand même enregistrée
                $this->addFlash('success', 'Traitement modifié avec succès !');
                $this->addFlash('warning', 'Note : L\'email de notification n\'a pas pu être envoyé.');
            }

            return $this->redirectToRoute('back_contact');
        }

        return $this->render('back/traitement_edit.html.twig', [
            'feedback' => $feedback,
            'traitement' => $traitement,
        ]);
    }

    /**
     * SUPPRIMER un traitement
     */
    #[Route('/traitement/{id}/delete', name: 'traitement_delete', methods: ['POST'])]
    public function deleteTraitement(
        Request $request,
        Traitement $traitement,
        EntityManagerInterface $em
    ): Response {
        // Récupérer le feedback associé
        $feedback = $traitement->getFeedback();

        // Vérifier le token CSRF
        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $traitement->getId(), $token)) {
            // Remettre le feedback en "en_attente"
            $feedback->setEtatfeedback('en_attente');

            // Supprimer le traitement
            $em->remove($traitement);
            $em->flush();

            $this->addFlash('success', 'Traitement supprimé avec succès !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('back_contact');
    }

    /**
     * EXPORT PDF (routes factices si PdfExportService n'existe pas)
     */
    #[Route('/feedback/{id}/export-pdf', name: 'feedback_export_pdf')]
    public function exportFeedbackPdf(Feedback $feedback): Response
    {
        $this->addFlash('info', 'Fonctionnalité d\'export PDF en cours de développement.');
        return $this->redirectToRoute('back_contact');
    }

    #[Route('/feedbacks/export-all-pdf', name: 'feedbacks_export_all_pdf')]
    public function exportAllFeedbacksPdf(FeedbackRepository $feedbackRepo): Response
    {
        $this->addFlash('info', 'Fonctionnalité d\'export PDF en cours de développement.');
        return $this->redirectToRoute('back_contact');
    }
}
