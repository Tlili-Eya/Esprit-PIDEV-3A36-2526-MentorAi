<?php

namespace App\Controller;

use App\Entity\Carnet;
use App\Entity\ProfilApprentissage;
use App\Entity\Humeur;
use App\Entity\ChatMessage;
use App\Repository\HumeurRepository;
use App\Repository\ProfilApprentissageRepository;
use App\Repository\ChatMessageRepository;
use Cloudinary\Configuration\Configuration;
use Cloudinary\Uploader;
use Doctrine\ORM\EntityManagerInterface;
use Smalot\PdfParser\Parser as PdfParser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

class CourController extends AbstractController
{
    private $httpClient;
    private $entityManager;
    private $humeurRepo;
    private $profilRepo;
    private $chatMessageRepo;
    private $groqApiKey;
    private $elevenLabsApiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        HumeurRepository $humeurRepo,
        ProfilApprentissageRepository $profilRepo,
        ChatMessageRepository $chatMessageRepo,
        string $groqApiKey,
        string $elevenLabsApiKey,
        string $cloudName,
        string $cloudApiKey,
        string $cloudApiSecret
    ) {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->humeurRepo = $humeurRepo;
        $this->profilRepo = $profilRepo;
        $this->chatMessageRepo = $chatMessageRepo;
        $this->groqApiKey = $groqApiKey;
        $this->elevenLabsApiKey = $elevenLabsApiKey;

        // Initialize Cloudinary
        Configuration::instance([
            'cloud' => [
                'cloud_name' => $cloudName,
                'api_key'    => $cloudApiKey,
                'api_secret' => $cloudApiSecret,
            ],
            'url' => [
                'secure' => true
            ]
        ]);
    }

    // ==================== PAGE DE CHAT ====================
    #[Route('/course-details', name: 'app_cour_chat')]
    public function chat(Request $request, SessionInterface $session): Response
    {
        // Récupérer le premier profil disponible (à adapter avec authentification plus tard)
        $profil = $this->profilRepo->findOneBy([]);
        if (!$profil) {
            $this->addFlash('info', 'Pour un meilleur accompagnement, passez d’abord le test de personnalité.');
            return $this->redirectToRoute('personality_test');
        }

        $history = $session->get('chat_history', []);
        if (empty($history)) {
            $welcome = $this->generateWelcomeMessage($profil);
            $history[] = ['role' => 'assistant', 'content' => $welcome];
            $session->set('chat_history', $history);
        }

        return $this->render('front/course-details.html.twig', [
            'history' => $history,
        ]);
    }

    // ==================== API ENVOI MESSAGE ====================
    #[Route('/api/chat/message', name: 'app_chat_message', methods: ['POST'])]
    public function sendMessage(Request $request, SessionInterface $session): JsonResponse
    {
        $profil = $this->profilRepo->findOneBy([]);
        if (!$profil) {
            return $this->json(['error' => 'Profil non trouvé'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $userMessage = $data['message'] ?? '';
        if (empty($userMessage)) {
            return $this->json(['error' => 'Message vide'], 400);
        }

        $documentContext = $session->get('active_document_context');
        $startTime = time();

        // Call AI
        $response = $this->callHuggingFaceChat($profil, $userMessage, $documentContext);
        $endTime = time();
        $learningDuration = $endTime - $startTime;

        // Save to database
        $userMsg = new ChatMessage();
        $userMsg->setProfilApprentissage($profil)
                ->setRole('user')
                ->setContenu($userMessage)
                ->setStartInteractionTime(new \DateTime())
                ->setLearningDurationSeconds($learningDuration);
        $this->entityManager->persist($userMsg);

        $assistantMsg = new ChatMessage();
        $assistantMsg->setProfilApprentissage($profil)
                     ->setRole('assistant')
                     ->setContenu($response)
                     ->setMetadata(['format' => $data['format'] ?? 'text']);
        $this->entityManager->persist($assistantMsg);

        // Analyze and update profile
        $this->analyzeAndUpdateProfile($profil, $userMessage, $response, $learningDuration);

        $this->entityManager->flush();

        $history = $session->get('chat_history', []);
        $history[] = ['role' => 'user', 'content' => $userMessage];
        $history[] = ['role' => 'assistant', 'content' => $response];
        $session->set('chat_history', $history);

        return $this->json([
            'response' => $response,
            'history' => $history,
        ]);
    }

    // ==================== UPLOAD DOCUMENT ====================
    #[Route('/api/chat/upload', name: 'app_chat_upload', methods: ['POST'])]
    public function uploadDocument(Request $request, SessionInterface $session): JsonResponse
    {
        $profil = $this->profilRepo->findOneBy([]);
        if (!$profil) {
            return $this->json(['error' => 'Profil non trouvé'], 400);
        }

        $file = $request->files->get('document');
        $link = $request->request->get('link');
        $text = '';

        if ($file) {
            try {
                $mimeType = $file->getMimeType();
                
                // Handle PDF
                if ($mimeType === 'application/pdf' || str_ends_with($file->getClientOriginalName(), '.pdf')) {
                    $parser = new PdfParser();
                    $pdf = $parser->parseFile($file->getPathname());
                    $text = $pdf->getText();
                } 
                // Handle images - just reference the uploaded image
                elseif (in_array($mimeType, ['image/jpeg', 'image/png', 'image/webp']) || 
                        in_array(mime_content_type($file->getPathname()), ['image/jpeg', 'image/png', 'image/webp'])) {
                    // Upload image to Cloudinary
                    $uploadResponse = Uploader::upload($file->getPathname(), [
                        'folder' => 'mentorai/documents',
                        'resource_type' => 'image',
                    ]);
                    $text = "Image chargée: {$file->getClientOriginalName()}\nLien: {$uploadResponse['secure_url']}\n\nAnalysez le contenu de cette image pour aider l'étudiant.";
                } else {
                    return $this->json(['error' => 'Format de fichier non supporté. Utilisez PDF, JPG, PNG ou WebP.'], 400);
                }
                
                // Limit to 2000 chars to manage Groq TPM limits
                $text = substr($text, 0, 2000);
            } catch (\Exception $e) {
                return $this->json(['error' => 'Impossible de lire le fichier: ' . $e->getMessage()], 400);
            }
        } elseif ($link) {
            $text = "Lien YouTube/URL fourni: $link";
        } else {
            return $this->json(['error' => 'Veuillez ajouter un fichier ou un lien'], 400);
        }

        $session->set('active_document_context', $text);

        // Get format recommendation
        $recommendation = $this->determineOptimalFormat(
            $profil->getFormatPréféré(),
            $profil->getVitesseApprentissage(),
            7
        );

        return $this->json([
            'success' => true,
            'message' => 'Document chargé. Quel format préférez-vous ?',
            'recommended_format' => $recommendation['primary'],
            'available_formats' => ['text', 'audio', 'mindmap', 'quiz'],
        ]);
    }

    // ==================== GENERATE SUMMARY ====================
    #[Route('/api/chat/summarize', name: 'app_chat_summarize', methods: ['POST'])]
    public function generateSummary(Request $request, SessionInterface $session): JsonResponse
    {
        $profil = $this->profilRepo->findOneBy([]);
        if (!$profil) {
            return $this->json(['error' => 'Profil non trouvé'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $format = $data['format'] ?? 'text';
        $context = $session->get('active_document_context');

        if (!$context) {
            return $this->json(['error' => 'Aucun document chargé'], 400);
        }

        $summary = $this->generateContentSummary($context, $profil, $format);

        if ($format === 'mindmap') {
            $mindMap = $this->generateMindMap($summary);
            return $this->json([
                'format' => 'mindmap',
                'text' => $summary,
                'mindmap' => $mindMap,
            ]);
        }

        if ($format === 'audio') {
            $audioContent = $this->textToSpeech($summary);
            if ($audioContent) {
                $cloudinaryResponse = Uploader::upload('data:audio/mp3;base64,' . base64_encode($audioContent), [
                    'resource_type' => 'video',
                    'public_id' => 'audio_' . uniqid(),
                ]);
                $audioUrl = $cloudinaryResponse['secure_url'];

                if (!empty($data['saveToCarnet'])) {
                    $this->saveToCarnet($profil, 'Résumé audio', $summary, $audioUrl);
                }

                return $this->json([
                    'format' => 'audio',
                    'text' => $summary,
                    'audio_url' => $audioUrl,
                ]);
            }
        }

        return $this->json([
            'format' => 'text',
            'text' => $summary,
        ]);
    }

    // ==================== RECOMMEND CONTENT FORMAT ====================
    #[Route('/api/chat/recommend-format', name: 'app_recommend_format', methods: ['POST'])]
    public function recommendFormat(Request $request): JsonResponse
    {
        $profil = $this->profilRepo->findOneBy([]);
        if (!$profil) {
            return $this->json(['error' => 'Profil non trouvé'], 400);
        }

        $humeur = $this->humeurRepo->findOneBy(['profilApprentissage' => $profil], ['creeLe' => 'DESC']);
        $valeurHumeur = $humeur ? $humeur->getValeurHumeur() : 7;

        $format = $profil->getFormatPréféré();
        $vitesse = $profil->getVitesseApprentissage();
        $style = $profil->getStyleMotivation();

        // Determine optimal format
        $recommendation = $this->determineOptimalFormat($format, $vitesse, $valeurHumeur);

        return $this->json([
            'recommended' => $recommendation['primary'],
            'alternatives' => $recommendation['alternatives'],
            'reason' => $recommendation['reason'],
        ]);
    }

    // ==================== GENERATE QUIZ ====================
    #[Route('/api/chat/quiz', name: 'app_generate_quiz', methods: ['POST'])]
    public function generateQuiz(Request $request, SessionInterface $session): JsonResponse
    {
        $profil = $this->profilRepo->findOneBy([]);
        if (!$profil) {
            return $this->json(['error' => 'Profil non trouvé'], 400);
        }

        $context = $session->get('active_document_context');
        if (!$context) {
            return $this->json(['error' => 'Aucun document chargé'], 400);
        }

        $quiz = $this->generateAdaptiveQuiz($context, $profil);

        return $this->json([
            'quiz' => $quiz,
        ]);
    }

    // ==================== MÉTHODES PRIVÉES ====================

    private function generateWelcomeMessage(ProfilApprentissage $profil, bool $needsTest): string
    {
        $user = $profil->getUtilisateur();
        $prenom = $user && method_exists($user, 'getPrenom') ? $user->getPrenom() : 'étudiant';

        $humeur = $this->humeurRepo->findOneBy(['profilApprentissage' => $profil], ['creeLe' => 'DESC']);
        $valeurHumeur = $humeur ? $humeur->getValeurHumeur() : 7;

        $type = $profil->getTypePers() ?? 'passionné';
        $style = $profil->getStyleMotivation() ?? 'encouragement';

        if ($needsTest) {
            $message = "Bonjour $prenom ! 👋\n\n";
            $message .= "Bienvenue dans votre assistant d'étude personnalisé. Pour que je puisse mieux vous aider, ";
            $message .= "il est important que vous passiez d'abord le test de personnalité.\n\n";
            $message .= "Ce test me permettra de :\n";
            $message .= "✓ Adapter le contenu à votre style d'apprentissage\n";
            $message .= "✓ Vous proposer les formats qui vous conviennent (texte, audio, mindmaps)\n";
            $message .= "✓ Gérer votre charge de travail selon votre humeur\n\n";
            $message .= "Prêt à commencer le test ?";
        } else {
            $mood = $valeurHumeur >= 7 ? "dynamique" : "encourageant";
            $message = "Bonjour $prenom ! 🎯\n\n";
            $message .= "Je vois que vous êtes un $type avec un style de motivation basé sur le $style.\n";
            if ($valeurHumeur < 7) {
                $message .= "Votre humeur du jour est un peu basse, nous allons y aller doucement et progressivement.\n";
            }
            $message .= "\nQu'aimeriez-vous faire ?\n";
            $message .= "• 📚 Charger un cours (PDF/YouTube)\n";
            $message .= "• ❓ Poser une question\n";
            $message .= "• 🧠 Générer des flashcards\n";
            $message .= "• 📊 Prendre un quiz adaptatif\n";
        }

        return $message;
    }

    private function callHuggingFaceChat(ProfilApprentissage $profil, string $userMessage, ?string $context = null): string
    {
        $humeur = $this->humeurRepo->findOneBy(['profilApprentissage' => $profil], ['creeLe' => 'DESC']);
        $valeurHumeur = $humeur ? $humeur->getValeurHumeur() : 7;

        $user = $profil->getUtilisateur();
        $prenom = $user && method_exists($user, 'getPrenom') ? $user->getPrenom() : 'utilisateur';

        // IMPORTANT: Groq free tier has TPM limit - keep context small!
        $contextSnippet = '';
        if ($context) {
            // Truncate context to 300 chars max to avoid TPM limit
            $contextSnippet = substr($context, 0, 300);
            $contextSnippet = "\n\nDocument snippet: " . $contextSnippet . (strlen($context) > 300 ? '...' : '');
        }

        $system = "Tu es un assistant d'étude pour $prenom.
Personnalité: {$profil->getTypePers()}
Humeur: $valeurHumeur/10
Matières fortes: {$profil->getMatieresFortes()}
Format préféré: {$profil->getFormatPréféré()}
Motivation: {$profil->getStyleMotivation()}$contextSnippet";

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                    'max_tokens' => 300,
                    'temperature' => 0.7,
                ],
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? 'Désolé, je n\'ai pas pu générer de réponse.';
        } catch (ClientExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $content = $e->getResponse()->getContent(false);
            $msg = "Groq API error ($statusCode): $content";
            error_log($msg);
            return "Erreur API Groq: $statusCode - $content";
        } catch (\Exception $e) {
            $msg = 'Erreur inattendue Groq: ' . $e->getMessage() . ' | ' . get_class($e);
            error_log($msg);
            error_log('Stack: ' . $e->getTraceAsString());
            return "Erreur: " . $e->getMessage();
        }
    }

    private function summarizeText(string $text, ProfilApprentissage $profil): string
    {
        $style = $profil->getVitesseApprentissage() === 'lent' ? 'très détaillé avec des analogies simples' : 'concis et direct';
        $prompt = "Résume ce texte de manière $style en mettant l'accent sur les {$profil->getMatieresFortes()}.\n\nTexte:\n" . substr($text, 0, 1000);

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 400,
                    'temperature' => 0.7,
                ],
            ]);

            $data = $response->toArray();
            return $data['choices'][0]['message']['content'] ?? 'Résumé non disponible.';
        } catch (\Exception $e) {
            return 'Résumé non disponible (erreur technique).';
        }
    }

    private function generateContentSummary(string $text, ProfilApprentissage $profil, string $format): string
    {
        return $this->summarizeText($text, $profil);
    }

    private function textToSpeech(string $text): ?string
    {
        try {
            $response = $this->httpClient->request('POST', 'https://api.elevenlabs.io/v1/text-to-speech/21m00Tcm4TlvDq8ikWAM/stream', [
                'headers' => [
                    'xi-api-key' => $this->elevenLabsApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'text' => substr($text, 0, 2000),
                    'model_id' => 'eleven_monolingual_v1',
                    'voice_settings' => [
                        'stability' => 0.5,
                        'similarity_boost' => 0.75,
                    ],
                ],
            ]);

            $audioData = $response->getContent();
            $uploadResponse = Uploader::upload('data:audio/mpeg;base64,' . base64_encode($audioData), [
                'resource_type' => 'video',
                'folder' => 'mentorai/audio',
            ]);

            return $uploadResponse['secure_url'] ?? null;
        } catch (\Exception $e) {
            error_log('ElevenLabs TTS Error: ' . $e->getMessage());
            return null;
        }
    }

    private function generateMindMap(string $summary): array
    {
        // Parse summary into structured mindmap JSON
        $lines = explode("\n", $summary);
        $mindmap = [
            'title' => 'Résumé',
            'children' => [],
        ];

        $currentParent = null;
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (substr_count($line, '-') === 0 || str_starts_with($line, '##')) {
                $currentParent = ['text' => $line, 'children' => []];
                $mindmap['children'][] = $currentParent;
            } elseif (str_starts_with($line, '-')) {
                if ($currentParent) {
                    $currentParent['children'][] = ['text' => substr($line, 1)];
                }
            }
        }

        return $mindmap;
    }

    private function generateAdaptiveQuiz(string $content, ProfilApprentissage $profil): array
    {
        $prompt = "Crée 5 questions de quiz basées sur ce contenu. Format: question | réponse A | réponse B | réponse C | réponse D | bonne réponse\n\nContenu:\n" . substr($content, 0, 500);

        try {
            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 600,
                    'temperature' => 0.7,
                ],
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Parse into structured quiz
            $questions = [];
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                if (trim($line) && str_contains($line, '|')) {
                    $parts = array_map('trim', explode('|', $line));
                    if (count($parts) >= 5) {
                        $questions[] = [
                            'question' => $parts[0],
                            'options' => [$parts[1], $parts[2], $parts[3], $parts[4]],
                            'correct' => intval($parts[5] ?? 0),
                        ];
                    }
                }
            }

            return $questions;
        } catch (\Exception $e) {
            return [];
        }
    }

    private function determineOptimalFormat(string $preferedFormat = null, int $vitesse = null, int $humeur = 7): array
    {
        if ($humeur < 5) {
            // Low mood: easier, visual formats
            return [
                'primary' => 'mindmap',
                'alternatives' => ['text', 'audio', 'quiz'],
                'reason' => 'Les mindmaps aident à rester motivé avec un visuel aéré.',
            ];
        }

        if ($vitesse >= 8) {
            // Fast learner: text or dense content
            return [
                'primary' => 'text',
                'alternatives' => ['quiz', 'mindmap', 'audio'],
                'reason' => 'Vous apprenez vite, du texte dense vous convient.',
            ];
        }

        // Default: match preference  
        $primary = $preferedFormat ?? 'mindmap';

        return [
            'primary' => $primary,
            'alternatives' => ['text', 'audio', 'mindmap', 'quiz'],
            'reason' => 'Format adapté à vos préférences.',
        ];
    }

    private function analyzeAndUpdateProfile(ProfilApprentissage $profil, string $userMessage, string $response, int $learningDuration): void
    {
        // Update learning time
        if (!$profil->getTempsMoyenApprentissage()) {
            $profil->setTempsMoyenApprentissage($learningDuration);
        } else {
            // Average with existing time
            $avg = (($profil->getTempsMoyenApprentissage() + $learningDuration) / 2);
            $profil->setTempsMoyenApprentissage((int)$avg);
        }

        // Analyze question difficulty
        if (preg_match('/pourquoi|comment|expliquer/', strtolower($userMessage)) || strlen($userMessage) > 100) {
            // Deep question - increase concentration level
            $profil->setNiveauConcentration(min(10, ($profil->getNiveauConcentration() ?? 7) + 1));
        }

        // Analyze response length (more engagement)
        if (strlen($response) > 300) {
            $profil->setNiveauConcentration(min(10, ($profil->getNiveauConcentration() ?? 7) + 1));
        }

        $this->entityManager->persist($profil);
    }

    private function saveToCarnet(ProfilApprentissage $profil, string $titre, string $contenu, string $url): void
    {
        $user = $profil->getUtilisateur();
        if (!$user) {
            return;
        }

        $carnet = new Carnet();
        $carnet->setTitre($titre);
        $carnet->setContenu($contenu);
        $carnet->setAttachments([$url]);
        $carnet->setDateCreation(new \DateTime());
        $carnet->setUtilisateurs($user);

        $this->entityManager->persist($carnet);
        $this->entityManager->flush();
    }

    private function searchYoutubeVideos(string $query): array
    {
        try {
            // Using YouTube's public search API via query parameter
            $searchUrl = 'https://www.youtube.com/results?search_query=' . urlencode($query);
            
            // Return embedded format for safe iframe embedding
            return [
                'search_url' => $searchUrl,
                'embed_html' => '<iframe width="100%" height="400" src="https://www.youtube.com/embed/videoseries?list=PLxxx" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>',
                'query' => $query,
                'note' => 'Search YouTube for: ' . $query,
            ];
        } catch (\Exception $e) {
            return [];
        }
    }
}