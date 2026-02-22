<?php

namespace App\Controller;

use App\Entity\Feedback;
use App\Entity\Utilisateur;
use App\Repository\FeedbackRepository;
use App\Repository\UtilisateurRepository;
use App\Service\EmailNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use Symfony\Component\Security\Http\Attribute\IsGranted;

// ✅ Préfixe de route '/feedback' + préfixe de nom 'front_feedback_'
#[Route('/feedback', name: 'front_feedback_')]
#[IsGranted('ROLE_USER')]
final class FeedbackController extends AbstractController
{
    // ============================================================
    // ✅ AUTHENTIFICATION RÉELLE - UTILISATEUR CONNECTÉ
    // ============================================================
    
    private function getCurrentUser(): Utilisateur
    {
        $user = $this->getUser();
        
        if (!$user instanceof Utilisateur) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }
        
        return $user;
    }

    private function getNewTreatedFeedbackCount(
        Request $request,
        FeedbackRepository $repo,
        Utilisateur $user
    ): int {
        $feedbacks = $repo->findBy(['utilisateur' => $user]);
        $session = $request->getSession();
        $seenFeedbackIds = $session->get('seen_treated_feedbacks', []);
        
        $newTreatedCount = 0;
        foreach ($feedbacks as $feedback) {
            $etat = strtolower($feedback->getEtatfeedback() ?? '');
            if (($etat === 'traite' || $etat === 'traité') && !in_array($feedback->getId(), $seenFeedbackIds)) {
                $newTreatedCount++;
            }
        }
        
        return $newTreatedCount;
    }

    // ============================================================
    // ROUTES FEEDBACK
    // ============================================================

    /**
     * AJOUT FEEDBACK
     * Route: /feedback/add
     * Nom: front_feedback_add (pas front_feedback_feedback_add !)
     */
    #[Route('/add', name: 'add', methods: ['POST'])]
    public function addFeedback(
        Request $request,
        EntityManagerInterface $em,
        ValidatorInterface $validator,
        FeedbackRepository $feedbackRepo,
        EmailNotificationService $emailService
    ): Response {
        $feedback = new Feedback();

        $typeFeedback = $request->request->get('type_feedback');
        $contenu = $request->request->get('contenu');
        $rating = $request->request->get('rating');

        $feedback->setTypefeedback($typeFeedback);
        $feedback->setContenu($contenu);
        $feedback->setNote((int)$rating);
        $feedback->setEtatfeedback('en_attente');
        $feedback->setDatefeedback(new \DateTime());
        
        $user = $this->getCurrentUser();
        $feedback->setUtilisateur($user);

        $errors = $validator->validate($feedback);
        
        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }
            return $this->redirectToRoute('front_contact');
        }

        $em->persist($feedback);
        $em->flush();

        try {
            $emailService->sendFeedbackReceivedNotification($feedback);
            $this->addFlash('success', 'Votre feedback a été envoyé avec succès ! Vous allez recevoir un email de confirmation.');
        } catch (\Exception $e) {
            $this->addFlash('success', 'Votre feedback a été envoyé avec succès !');
            $this->addFlash('warning', 'Note : L\'email de confirmation n\'a pas pu être envoyé.');
        }

        return $this->redirectToRoute('front_feedback_list');
    }

    /**
     * LISTE FEEDBACK
     * Route: /feedback/list
     * Nom: front_feedback_list
     */
    #[Route('/list', name: 'list')]
    public function feedbackList(
        Request $request,
        FeedbackRepository $repo
    ): Response {
        $user = $this->getCurrentUser();

        $searchTerm = $request->query->get('search', '');
        $sortOrder = $request->query->get('sort', 'DESC');

        if (!in_array($sortOrder, ['DESC', 'ASC'])) {
            $sortOrder = 'DESC';
        }

        $feedbacks = $repo->searchByUser($user, $searchTerm, $sortOrder);

        $session = $request->getSession();
        $seenFeedbackIds = $session->get('seen_treated_feedbacks', []);
        
        foreach ($feedbacks as $feedback) {
            $etat = strtolower($feedback->getEtatfeedback() ?? '');
            if (($etat === 'traite' || $etat === 'traité') && !in_array($feedback->getId(), $seenFeedbackIds)) {
                $seenFeedbackIds[] = $feedback->getId();
            }
        }
        
        $session->set('seen_treated_feedbacks', $seenFeedbackIds);

        return $this->render('front/feedback_list.html.twig', [
            'feedbacks' => $feedbacks,
            'newTreatedCount' => 0,
            'searchTerm' => $searchTerm,
            'sortOrder' => $sortOrder
        ]);
    }

    /**
     * MODIFIER FEEDBACK
     * Route: /feedback/{id}/edit
     * Nom: front_feedback_edit
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Feedback $feedback,
        EntityManagerInterface $em,
        ValidatorInterface $validator
    ): Response {
        $user = $this->getCurrentUser();

        if ($feedback->getUtilisateur() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez pas modifier ce feedback.');
            return $this->redirectToRoute('front_feedback_list');
        }

        $etat = strtolower($feedback->getEtatfeedback() ?? '');
        
        if ($etat === 'traite' || $etat === 'traité') {
            $this->addFlash('error', 'Ce feedback a déjà été traité et ne peut plus être modifié.');
            return $this->redirectToRoute('front_feedback_list');
        }

        if ($request->isMethod('POST')) {
            $typeFeedback = $request->request->get('type_feedback');
            $contenu = $request->request->get('contenu');
            $rating = $request->request->get('rating');

            $feedback->setTypefeedback($typeFeedback);
            $feedback->setContenu($contenu);
            $feedback->setNote((int)$rating);

            $errors = $validator->validate($feedback);
            
            if (count($errors) > 0) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
                return $this->redirectToRoute('front_feedback_edit', ['id' => $feedback->getId()]);
            }

            $em->flush();

            $this->addFlash('success', 'Feedback modifié avec succès !');
            return $this->redirectToRoute('front_feedback_list');
        }

        return $this->render('front/edit.html.twig', [
            'feedback' => $feedback,
        ]);
    }

    /**
     * SUPPRIMER FEEDBACK
     * Route: /feedback/{id}/delete
     * Nom: front_feedback_delete
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Feedback $feedback,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getCurrentUser();

        if ($feedback->getUtilisateur() !== $user) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer ce feedback.');
            return $this->redirectToRoute('front_feedback_list');
        }

        $etat = strtolower($feedback->getEtatfeedback() ?? '');
        
        if ($etat === 'traite' || $etat === 'traité') {
            $this->addFlash('error', 'Ce feedback a déjà été traité et ne peut plus être supprimé.');
            return $this->redirectToRoute('front_feedback_list');
        }

        $token = $request->request->get('_token');
        if ($this->isCsrfTokenValid('delete' . $feedback->getId(), $token)) {
            $em->remove($feedback);
            $em->flush();
            $this->addFlash('success', 'Feedback supprimé avec succès !');
        } else {
            $this->addFlash('error', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('front_feedback_list');
    }
}
