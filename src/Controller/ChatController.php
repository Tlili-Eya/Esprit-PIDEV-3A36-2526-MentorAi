<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Service\GroqService;
use App\Repository\ConversationRepository;
use App\Repository\PlanActionsRepository;
use App\Repository\SortieAIRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\ExpressionLanguage\Expression;

class ChatController extends AbstractController
{
    #[Route('/chat', name: 'app_chat')]
    #[IsGranted(new Expression("is_granted('ROLE_ENSEIGNANT') or is_granted('ROLE_ADMINM')"))]
    public function index(): Response
    {
        // Déterminer le template selon le rôle
        if ($this->isGranted('ROLE_ADMINM')) {
            $template = 'front/admin/chat_ia.html.twig';
        } else {
            $template = 'front/enseignant/chat_ia.html.twig';
        }
        
        return $this->render($template);
    }

    #[Route('/chat/send', name: 'app_chat_send', methods: ['POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_ENSEIGNANT') or is_granted('ROLE_ADMINM')"))]
    public function send(
        Request $request, 
        GroqService $groq, 
        EntityManagerInterface $entityManager, 
        ConversationRepository $conversationRepository,
        PlanActionsRepository $planRepository,
        SortieAIRepository $aiRepository,
        \App\Repository\ReferenceArticleRepository $articleRepository,
        \Psr\Log\LoggerInterface $logger
    ): JsonResponse
    {
        try {
            $messageContent = $request->request->get('message');
            
            if (!$messageContent) {
                return $this->json(['success' => false, 'error' => 'Message vide'], 400);
            }

            $user = $this->getUser();
            $session = $request->getSession();
            
            $conversationId = $session->get('current_conversation_id');
            $conversation = $conversationId ? $conversationRepository->find($conversationId) : null;

            $history = [];
            if ($conversation) {
                foreach ($conversation->getMessages() as $msg) {
                    $history[] = ['role' => $msg->getRole(), 'content' => $msg->getContent()];
                }
            }

            if (!$conversation) {
                $conversation = new Conversation();
                $conversation->setUser($user);
                $conversation->setTitre(substr($messageContent, 0, 50) . '...');
                $entityManager->persist($conversation);
                $entityManager->flush();
                $session->set('current_conversation_id', $conversation->getId());
            }

            $userMessage = new Message();
            $userMessage->setConversation($conversation);
            $userMessage->setRole('user');
            $userMessage->setContent($messageContent);
            $userMessage->setCreatedAt(new \DateTimeImmutable());
            $entityManager->persist($userMessage);

            $userRole = $this->isGranted('ROLE_ADMINM') ? 'ROLE_ADMINM' : 'ROLE_ENSEIGNANT';
            
            // Liste des étudiants pour le contexte
            $students = $entityManager->getRepository(\App\Entity\Utilisateur::class)->findBy(['role' => 'ETUDIANT']);
            $studentList = "";
            foreach ($students as $s) {
                $studentList .= "- " . $s->getPrenom() . " " . $s->getNom() . " (ID: " . $s->getId() . ")\n";
            }

            // Construction du contexte riche
            $context = "Tu es MentorAI, un assistant pédagogique haute performance. L'utilisateur actuel est " . $user->getNom() . " (Rôle: " . $userRole . ").\n";
            $context .= "ÉTUDIANTS ACTUELS DANS LA BASE :\n" . ($studentList ?: "Aucun étudiant trouvé.") . "\n\n";
            $context .= "TES MISSIONS :\n";
            $context .= "1. Analyser les situations critiques (stress, chute de notes, humeur basse).\n";
            $context .= "2. Proposer des plans d'actions concrets.\n";
            $context .= "3. Format de réponse : Toujours finir par un bloc JSON structuré comme ceci :\n";
            $context .= "```json\n";
            $context .= "{\n";
            $context .= "  \"type_ai\": \"ALERTE|COMMUNICATION|ANALYSE\",\n";
            $context .= "  \"decisions\": [\n";
            $context .= "    {\"student_name\": \"Nom\", \"action\": \"Action\", \"details\": \"Détails\", \"priority\": \"Eleve|Moyen|Faible\"}\n";
            $context .= "  ]\n";
            $context .= "}\n";
            $context .= "```\n";
            
            $fullPrompt = "CONTEXTE : " . $context . "\n\nMESSAGE DE L'UTILISATEUR : " . $messageContent;
            $result = $groq->sendMessage($fullPrompt, $history, $userRole);
            
            if ($result['success']) {
                $assistantMessage = new Message();
                $assistantMessage->setConversation($conversation);
                $assistantMessage->setRole('assistant');
                $assistantMessage->setContent($result['response']);
                $assistantMessage->setCreatedAt(new \DateTimeImmutable());
                $entityManager->persist($assistantMessage);
                
                // Analyse et persistance automatisée
                try {
                    $allJsonContent = $this->extractAllJson($result['response']);
                    foreach ($allJsonContent as $jsonContent) {
                        $data = json_decode($jsonContent, true);
                        if ($data && isset($data['decisions'])) {
                            foreach ($data['decisions'] as $decisionData) {
                                // Chercher l'étudiant
                                $student = null;
                                if (isset($decisionData['student_name'])) {
                                    $student = $entityManager->getRepository(\App\Entity\Utilisateur::class)
                                        ->findOneByNameOrPrenom($decisionData['student_name']);
                                }

                                // Créer la Sortie AI
                                $sortie = new \App\Entity\SortieAI();
                                $sortie->setEtudiant($student);
                                $typeStr = $data['type_ai'] ?? 'ANALYSE';
                                $sortie->setTypeSortie($typeStr === 'ALERTE' ? \App\Enum\TypeSortie::Alerte : \App\Enum\TypeSortie::Analyse);
                                $sortie->setCriticite(\App\Enum\Criticite::Moyen);
                                $sortie->setCible($userRole === 'ROLE_ADMINM' ? \App\Enum\Cible::Administrateur : \App\Enum\Cible::Enseignant);
                                $sortie->setCategorieSortie($userRole === 'ROLE_ADMINM' ? \App\Enum\CategorieSortie::Strategique : \App\Enum\CategorieSortie::Pedagogique);
                                $sortie->setContenu($decisionData['details'] ?? $result['response']);
                                $sortie->setStatut(\App\Enum\StatutSortie::Nouveau);
                                $entityManager->persist($sortie);

                                // Créer le Plan d'Action
                                $plan = new \App\Entity\PlanActions();
                                $plan->setEtudiant($student);
                                $plan->setDecision($decisionData['action'] ?? 'Action suggérée');
                                $plan->setDescription($decisionData['details'] ?? 'Consulter l\'analyse AI');
                                $plan->setAuteur($user);
                                $plan->setStatut(\App\Enum\Statut::EnAttente);
                                $plan->setSortieAI($sortie);
                                $plan->setCategorie($sortie->getCategorieSortie());
                                $entityManager->persist($plan);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    $logger->error("AI Logic Error: " . $e->getMessage());
                }
                
                $entityManager->flush();
            }

            return $this->json($result);

        } catch (\Throwable $e) {
            $logger->critical("Chat Critical Error: " . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            return $this->json([
                'success' => false,
                'error' => "Une erreur critique est survenue dans le serveur : " . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/chat/history', name: 'app_chat_history', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_ENSEIGNANT') or is_granted('ROLE_ADMINM')"))]
    public function history(Request $request, ConversationRepository $conversationRepository): JsonResponse
    {
        $session = $request->getSession();
        $conversationId = $session->get('current_conversation_id');
        
        $history = [];
        if ($conversationId) {
             $conversation = $conversationRepository->find($conversationId);
             if ($conversation && $conversation->getUser() === $this->getUser()) {
                 foreach ($conversation->getMessages() as $msg) {
                     $history[] = [
                         'role' => $msg->getRole(),
                         'content' => $msg->getContent()
                     ];
                 }
             }
        }

        return $this->json([
            'success' => true,
            'history' => $history
        ]);
    }

    #[Route('/chat/conversations', name: 'app_chat_conversations', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_ENSEIGNANT') or is_granted('ROLE_ADMINM')"))]
    public function conversations(ConversationRepository $conversationRepository): JsonResponse
    {
        $conversations = $conversationRepository->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        $data = [];
        foreach ($conversations as $conv) {
            $data[] = [
                'id' => $conv->getId(),
                'titre' => $conv->getTitre(),
                'date' => $conv->getCreatedAt()->format('d/m/Y H:i')
            ];
        }

        return $this->json([
            'success' => true,
            'conversations' => $data
        ]);
    }

    #[Route('/chat/load/{id}', name: 'app_chat_load', methods: ['GET'])]
    #[IsGranted(new Expression("is_granted('ROLE_ENSEIGNANT') or is_granted('ROLE_ADMINM')"))]
    public function loadConversation(int $id, Request $request, ConversationRepository $conversationRepository): JsonResponse
    {
        $conversation = $conversationRepository->find($id);
        
        if (!$conversation || $conversation->getUser() !== $this->getUser()) {
            return $this->json(['success' => false, 'error' => 'Conversation non trouvée'], 404);
        }

        $request->getSession()->set('current_conversation_id', $conversation->getId());

        $history = [];
        foreach ($conversation->getMessages() as $msg) {
            $history[] = [
                'role' => $msg->getRole(),
                'content' => $msg->getContent()
            ];
        }

        return $this->json([
            'success' => true,
            'history' => $history,
            'titre' => $conversation->getTitre()
        ]);
    }

    #[Route('/chat/reset', name: 'app_chat_reset', methods: ['POST'])]
    #[IsGranted(new Expression("is_granted('ROLE_ENSEIGNANT') or is_granted('ROLE_ADMINM')"))]
    public function reset(Request $request): JsonResponse
    {
        $session = $request->getSession();
        $session->remove('current_conversation_id');
        
        return $this->json(['success' => true]);
    }

    #[Route('/chat/config', name: 'app_chat_config', methods: ['POST'])]
    #[IsGranted('ROLE_ADMINM')]  // Seulement les admins peuvent configurer
    public function config(Request $request): JsonResponse
    {
        $config = json_decode($request->getContent(), true);
        $session = $request->getSession();
        $session->set('chat_config', $config);
        
        return $this->json(['success' => true]);
    }

    #[Route('/chat/stats', name: 'app_chat_stats', methods: ['GET'])]
    #[IsGranted('ROLE_ADMINM')]  // Seulement les admins voient les stats
    public function stats(Request $request, ConversationRepository $conversationRepository): JsonResponse
    {
        $session = $request->getSession();
        $conversationId = $session->get('current_conversation_id');
        $messagesCount = 0;
        
        if ($conversationId) {
            $conversation = $conversationRepository->find($conversationId);
            if ($conversation) {
                $messagesCount = $conversation->getMessages()->count();
            }
        }
        
        // Compter toutes les conversations en base
        $totalConversations = $conversationRepository->count([]);
        
        return $this->json([
            'success' => true,
            'stats' => [
                'messages_in_session' => $messagesCount,
                'conversations_count' => $totalConversations,
                'user_role' => $this->isGranted('ROLE_ADMINM') ? 'admin' : 'enseignant'
            ]
        ]);
    }

    private function extractAllJson(string $text): array
    {
        $contents = [];
        
        // Extraction avec balises ```json
        if (preg_match_all('/```json\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $contents = array_merge($contents, $matches[1]);
        }
        
        // Si aucun match avec balises, tenter de trouver des structures JSON brutes
        if (empty($contents)) {
            if (preg_match_all('/\{[^{}]*(?:(?R)[^{}]*)*\}/s', $text, $matches)) {
                foreach ($matches[0] as $candidate) {
                    json_decode($candidate);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $contents[] = $candidate;
                    }
                }
            }
        }
        
        return array_unique($contents);
    }
}