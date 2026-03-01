<?php

namespace App\Controller;

use App\Entity\Carnet;
use App\Entity\ProfilApprentissage;
use App\Entity\Humeur;
use App\Entity\ChatMessage;
use App\Entity\Utilisateur;
use App\Repository\HumeurRepository;
use App\Repository\ProfilApprentissageRepository;
use App\Repository\ChatMessageRepository;
use App\Repository\PlanningEtudeRepository;
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
    private const FALLBACK_API_MESSAGE = "Désolé, je n'ai pas pu générer une réponse. Vérifiez que GROQ_API_KEY est bien définie dans votre fichier .env et que la clé est valide sur groq.com. Réessayez dans quelques instants.";

    private $httpClient;
    private $entityManager;
    private $humeurRepo;
    private $profilRepo;
    private $chatMessageRepo;
    private $planningRepo;
    private $groqApiKey;
    private $groqModel;
    private $elevenLabsApiKey;

    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        HumeurRepository $humeurRepo,
        ProfilApprentissageRepository $profilRepo,
        ChatMessageRepository $chatMessageRepo,
        PlanningEtudeRepository $planningRepo,
        string $groqApiKey,
        string $groqModel,
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
        $this->planningRepo = $planningRepo;
        $this->groqApiKey = $groqApiKey;
        $this->groqModel = $groqModel;
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

    /**
     * Sanitize a string to ensure valid UTF-8 encoding
     */
    private function sanitizeUtf8(string $text): string
    {
        // Remove invalid UTF-8 characters
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        // Replace any remaining invalid sequences
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
    }

    /**
     * Safe Groq API call with better error handling
     */
    private function callOpenRouter(array $messages, int $maxTokens = 250): string
    {
        if (empty(trim($this->groqApiKey ?? ''))) {
            error_log('CourController: GROQ_API_KEY is empty. Run: php bin/console cache:clear');
            return "La clé GROQ_API_KEY est vide. Vérifiez votre fichier .env puis exécutez : php bin/console cache:clear";
        }

        try {
            $payload = [
                'model' => !empty(trim($this->groqModel ?? '')) ? $this->groqModel : 'llama-3.3-70b-versatile',
                'messages' => $messages,
                'temperature' => 0.9,
                'max_tokens' => $maxTokens,
            ];

            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray();

            // Log full response for debugging
            error_log('Groq response: ' . json_encode($data));

            // Check for API errors
            if (isset($data['error'])) {
                $errorMsg = $data['error']['message'] ?? ($data['error']['type'] ?? 'Unknown error');
                error_log('Groq error: ' . json_encode($data['error']));
                return "Groq : " . (is_string($errorMsg) ? $errorMsg : json_encode($errorMsg)) . ". Vérifiez votre clé sur groq.com/keys";
            }

            // Check if response has expected structure
            if (!isset($data['choices'][0]['message']['content'])) {
                error_log('Groq unexpected structure: ' . json_encode($data));
                return self::FALLBACK_API_MESSAGE;
            }

            $content = trim($data['choices'][0]['message']['content']);
            if (empty($content)) {
                error_log('Groq returned empty content');
                return self::FALLBACK_API_MESSAGE;
            }

            return $content;

        } catch (ClientExceptionInterface $e) {
            $statusCode = $e->getResponse()->getStatusCode();
            $content = $e->getResponse()->getContent(false);
            error_log("Groq HTTP $statusCode: " . substr($content, 0, 500));
            if ($statusCode === 401) {
                return "Clé API Groq invalide ou expirée. Vérifiez sur groq.com/keys et mettez à jour .env";
            }
            if ($statusCode === 429) {
                return "Limite de requêtes Groq atteinte. Réessayez dans quelques minutes.";
            }
            return self::FALLBACK_API_MESSAGE;
        } catch (\Exception $e) {
            error_log('Groq: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            return self::FALLBACK_API_MESSAGE;
        }
    }

    /**
     * Return the learning profile for the current logged-in user.
     * If no user is logged, fall back to the latest profile.
     */
    private function getOrCreateProfilForCurrentUser(): ?ProfilApprentissage
    {
        $user = $this->getUser();

        if ($user instanceof Utilisateur) {
            $profil = $this->profilRepo->findOneBy(['Utilisateur' => $user]);
            if (!$profil) {
                $profil = new ProfilApprentissage();
                $profil->setUtilisateur($user);
                $this->entityManager->persist($profil);
                $this->entityManager->flush();
            }

            return $profil;
        }

        // Fallback: use latest profile if it exists (legacy behaviour)
        $profil = $this->profilRepo->findOneBy([], ['id' => 'DESC']);
        if ($profil) {
            return $profil;
        }

        // Absolute fallback: create an anonymous profile so that the chat always works
        $profil = new ProfilApprentissage();
        $this->entityManager->persist($profil);
        $this->entityManager->flush();

        return $profil;
    }

    /**
     * Map vitesse_apprentissage (1–10) to human label.
     */
    private function mapVitesseToLabel(?int $vitesse): string
    {
        if ($vitesse === null) {
            return 'normale';
        }
        if ($vitesse <= 3) {
            return 'lente';
        }
        if ($vitesse >= 8) {
            return 'rapide';
        }

        return 'normale';
    }

    /**
     * Look into PlanningEtude to detect an upcoming test/exam for this student.
     */
    private function getUpcomingExamContext(ProfilApprentissage $profil): ?string
    {
        $user = $profil->getUtilisateur();
        if (!$user instanceof Utilisateur) {
            return null;
        }

        $today = new \DateTimeImmutable('today');
        $end = $today->modify('+2 days');

        // Already ordered by date and heure in repository
        $plannings = $this->planningRepo->findByDateRange($today, $end);
        if (!$plannings) {
            return null;
        }

        foreach ($plannings as $planning) {
            // Only consider activities for this user when possible
            if ($planning->getUtilisateur() && $planning->getUtilisateur()->getId() !== $user->getId()) {
                continue;
            }

            $title = (string) $planning->getTitreP();
            $type = (string) $planning->getTypeActivite();
            $description = (string) $planning->getDescription();
            $haystack = mb_strtolower($title . ' ' . $type . ' ' . $description);

            if (str_contains($haystack, 'test') || str_contains($haystack, 'examen') || str_contains($haystack, 'exam')) {
                $matiere = $planning->getMatiere() ?: $planning->getTitreP() ?: $planning->getTypeActivite();
                $date = $planning->getDateSeance()?->format('d/m');

                if ($matiere && $date) {
                    return "Examen ou test à venir en {$matiere} le {$date}.";
                }

                return 'Examen ou test à venir très bientôt.';
            }
        }

        return null;
    }

    // ==================== PAGE DE CHAT ====================
    #[Route('/course-details', name: 'app_cour_chat')]
    public function chat(Request $request, SessionInterface $session): Response
    {
        $profil = $this->getOrCreateProfilForCurrentUser();
        if (!$profil) {
            throw $this->createNotFoundException('Profil d\'apprentissage introuvable');
        }

        // Si le type de personnalité n'est pas encore défini, on invite à faire le test
        $needsTest = !$profil->getTypePers();

        $history = $session->get('chat_history', []);
        if (empty($history)) {
            $welcome = $this->generateWelcomeMessage($profil, $needsTest);
            $history[] = ['role' => 'assistant', 'content' => $welcome];
            $session->set('chat_history', $history);
        }

        return $this->render('front/course-details.html.twig', [
            'history' => $history,
            'profil' => $profil,
            'needsPersonalityTest' => $needsTest,
        ]);
    }

    /**
     * Diagnostic: vérifie que la clé Groq est chargée et fonctionne.
     * Accès: GET /api/chat/test-api
     */
    #[Route('/api/chat/test-api', name: 'app_chat_test_api', methods: ['GET'])]
    public function testApi(): JsonResponse
    {
        $keySet = !empty(trim($this->groqApiKey ?? ''));
        $keyPreview = $keySet ? substr($this->groqApiKey, 0, 12) . '...' : '(vide)';

        $result = [
            'groq_key_loaded' => $keySet,
            'groq_key_preview' => $keyPreview,
            'message' => $keySet ? 'Clé chargée. Testez le chat.' : 'Clé vide. Ajoutez GROQ_API_KEY dans .env puis : php bin/console cache:clear',
        ];

        if ($keySet) {
            $testResponse = $this->callOpenRouter([['role' => 'user', 'content' => 'Réponds uniquement par: OK']], 10);
            $result['api_test'] = str_contains($testResponse, 'OK') || strlen($testResponse) < 100 ? 'ok' : 'error';
            $result['api_response_preview'] = substr($testResponse, 0, 80);
        }

        return $this->json($result);
    }

    // ==================== API ENVOI MESSAGE ====================
    #[Route('/api/chat/message', name: 'app_chat_message', methods: ['POST'])]
    public function sendMessage(Request $request, SessionInterface $session): JsonResponse
    {
        $responseFormat = 'text';

        try {
            $profil = $this->getOrCreateProfilForCurrentUser();
            if (!$profil) {
                return $this->json([
                    'response' => "Désolé, aucun profil d'apprentissage trouvé. Passez le test de personnalité pour commencer.",
                    'audioUrl' => null,
                    'format' => 'text',
                    'error' => 'Profil non trouvé',
                ], 200);
            }

            $data = json_decode($request->getContent(), true);
            $userMessage = $data['message'] ?? '';
            $format = $data['format'] ?? 'text';
            $responseFormat = $data['responseFormat'] ?? 'text';
            $audioData = $data['audioBase64'] ?? null;

            if ($audioData && !$userMessage) {
                $userMessage = $this->audioToText($audioData);
                if (!$userMessage) {
                    return $this->json([
                        'response' => "Impossible de convertir l'audio en texte. L'enregistrement vocal est désactivé pour le moment.",
                        'audioUrl' => null,
                        'format' => $responseFormat,
                    ], 200);
                }
            }

            if (empty($userMessage)) {
                return $this->json([
                    'response' => "Votre message semble vide. Tapez une question ou une demande et réessayez.",
                    'audioUrl' => null,
                    'format' => $responseFormat,
                ], 200);
            }

            $documentContext = $session->get('active_document_context');
            $startTime = time();

            $normalizedMessage = mb_strtolower($userMessage);
            $wantsAudioSummary = $documentContext && (
                str_contains($normalizedMessage, 'résumé audio') ||
                str_contains($normalizedMessage, 'resume audio') ||
                (str_contains($normalizedMessage, 'résumé') && str_contains($normalizedMessage, 'audio'))
            );

            if ($wantsAudioSummary) {
                $summary = $this->generateContentSummary($documentContext, $profil, 'text');
                $audioUrl = $this->textToSpeech($summary);
                // Return audio response regardless of UI selection when explicitly requested
                if ($audioUrl) {
                    $response = "🎵 Voici le résumé audio de votre document";
                } else {
                    $response = $summary; // Fallback to text if TTS fails
                }
            } else {
                $response = $this->callHuggingFaceChat($profil, $userMessage, $documentContext);
                $audioUrl = null;
                if ($responseFormat === 'audio') {
                    $audioUrl = $this->textToSpeech($response);
                }
            }

            // Ensure we never return empty - use fallback if API returned nothing useful
            if ($response === null || trim($response) === '') {
                $response = self::FALLBACK_API_MESSAGE;
            }

            $endTime = time();
            $learningDuration = $endTime - $startTime;

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
                         ->setMetadata(['format' => $responseFormat, 'audioUrl' => $audioUrl]);
            $this->entityManager->persist($assistantMsg);

            $this->analyzeAndUpdateProfile($profil, $userMessage, $response, $learningDuration, $responseFormat);
            $this->entityManager->flush();

            $history = $session->get('chat_history', []);
            $history[] = ['role' => 'user', 'content' => $userMessage, 'format' => 'text'];
            $history[] = [
                'role' => 'assistant',
                'content' => $response,
                'format' => $responseFormat,
                'audioUrl' => $audioUrl,
            ];
            $session->set('chat_history', $history);

            return $this->json([
                'response' => $response,
                'audioUrl' => $audioUrl,
                'format' => $responseFormat,
            ]);
        } catch (\Throwable $e) {
            error_log('Chat sendMessage error: ' . $e->getMessage());
            error_log($e->getTraceAsString());
            return $this->json([
                'response' => self::FALLBACK_API_MESSAGE,
                'audioUrl' => null,
                'format' => $responseFormat,
            ], 200);
        }
    }

    // ==================== UPLOAD DOCUMENT ====================
    #[Route('/api/chat/upload', name: 'app_chat_upload', methods: ['POST'])]
    public function uploadDocument(Request $request, SessionInterface $session): JsonResponse
    {
        $profil = $this->getOrCreateProfilForCurrentUser();
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
                    
                    // Generate AI summary of PDF
                    $pdfSummary = $this->summarizeText($text, $profil);
                    $text = "PDF: {$file->getClientOriginalName()}\n\nRésumé généré:\n" . $pdfSummary . "\n\n--- Contenu complet ---\n" . substr($text, 0, 1500);
                } 
                // Handle audio - NOT SUPPORTED (no transcription backend)
                elseif (in_array($mimeType, ['audio/mpeg', 'audio/wav', 'audio/webm', 'audio/ogg']) ||
                        in_array(pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION), ['mp3', 'wav', 'webm', 'ogg'])) {
                    return $this->json(['error' => 'Les fichiers audio ne sont pas supportés actuellement. Veuillez télécharger un PDF, une image ou un lien YouTube.'], 400);
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
                    return $this->json(['error' => 'Format de fichier non supporté. Utilisez PDF, Audio, JPG, PNG ou WebP.'], 400);
                }
                
                // Limit context for OpenRouter
                $text = substr($text, 0, 3000);
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
        $humeur = $this->humeurRepo->findOneBy(['profilApprentissage' => $profil], ['creeLe' => 'DESC']);
        $valeurHumeur = $humeur ? $humeur->getValeurHumeur() : 7;

        $recommendation = $this->determineOptimalFormat(
            $profil->getFormatPréféré(),
            $profil->getVitesseApprentissage(),
            $valeurHumeur
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
        $profil = $this->getOrCreateProfilForCurrentUser();
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
            $audioUrl = $this->textToSpeech($summary);
            if ($audioUrl) {
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
        $profil = $this->getOrCreateProfilForCurrentUser();
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
        $profil = $this->getOrCreateProfilForCurrentUser();
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

    // ==================== GENERATE FLASHCARDS ====================
    #[Route('/api/chat/flashcards', name: 'app_generate_flashcards', methods: ['POST'])]
    public function generateFlashcardsApi(Request $request, SessionInterface $session): JsonResponse
    {
        $profil = $this->getOrCreateProfilForCurrentUser();
        if (!$profil) {
            return $this->json(['error' => 'Profil non trouvé'], 400);
        }

        $context = $session->get('active_document_context');
        if (!$context) {
            return $this->json(['error' => 'Aucun document chargé'], 400);
        }

        $flashcards = $this->generateAdaptiveFlashcards($context, $profil);

        return $this->json([
            'flashcards' => $flashcards,
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
            if ($valeurHumeur < 7) {
                $message = "Bonjour $prenom, prêt pour une session de révision ?\n";
                $message .= "J'ai vu que ton humeur est un peu basse aujourd'hui, nous allons y aller doucement.\n\n";
            } else {
                $message = "Bonjour $prenom ! 🎯 Prêt pour une nouvelle session de révision ?\n\n";
            }

            $message .= "Tu es un profil $type avec un style de motivation basé sur le $style.\n";

            $examContext = $this->getUpcomingExamContext($profil);
            if ($examContext) {
                $message .= "\nDans ton planning, je vois aussi : $examContext\n";
                $message .= "Si tu veux, je peux me concentrer sur l'essentiel pour t'aider à gagner un maximum de points.\n";
            }

            $message .= "\nQue veux-tu faire maintenant ?\n";
            $message .= "• 📚 M'envoyer un cours (PDF/YouTube) pour que je le résume\n";
            $message .= "• ❓ Poser une question précise sur un chapitre\n";
            $message .= "• 🧠 Générer des flashcards ou fiches de révision\n";
            $message .= "• 📊 Lancer un quiz adaptatif pour t'entraîner\n";
        }

        return $message;
    }

    private function callHuggingFaceChat(ProfilApprentissage $profil, string $userMessage, ?string $context = null): string
    {
        $humeur = $this->humeurRepo->findOneBy(['profilApprentissage' => $profil], ['creeLe' => 'DESC']);
        $valeurHumeur = $humeur ? $humeur->getValeurHumeur() : 7;

        $user = $profil->getUtilisateur();
        $prenom = $user && method_exists($user, 'getPrenom') ? $user->getPrenom() : 'utilisateur';

        // Context management for OpenRouter - keep it short for speed
        $contextSnippet = '';
        if ($context) {
            $contextSnippet = substr($context, 0, 300);
            $contextSnippet = "\n\nDocument: " . $contextSnippet . (strlen($context) > 300 ? '...' : '');
        }

        $vitesseLabel = $this->mapVitesseToLabel($profil->getVitesseApprentissage());
        $formatPref = $profil->getFormatPréféré() ?? 'mindmap';
        $matieresFortes = $profil->getMatieresFortes() ?: 'non précisées';
        $matieresFaibles = $profil->getMatieresFaibles() ?: 'non précisées';
        $styleMotivation = $profil->getStyleMotivation() ?? 'encouragements positifs';
        $examContext = $this->getUpcomingExamContext($profil);

        $systemContext = "Tu es un assistant d'étude ultra personnalisé (style NotebookLM) pour $prenom.\n";
        $systemContext .= "Profil actuel de l'étudiant :\n";
        $systemContext .= "- Type de personnalité : {$profil->getTypePers()}\n";
        $systemContext .= "- Humeur du jour : $valeurHumeur/10\n";
        $systemContext .= "- Vitesse d'apprentissage : $vitesseLabel\n";
        $systemContext .= "- Matières fortes : $matieresFortes\n";
        $systemContext .= "- Matières faibles : $matieresFaibles\n";
        $systemContext .= "- Format préféré : $formatPref\n";
        $systemContext .= "- Style de motivation : $styleMotivation\n";

        if ($examContext) {
            $systemContext .= "- Contexte planning : $examContext\n";
        }

        if ($contextSnippet) {
            $systemContext .= "\nTu disposes aussi d'un document de cours ou de révision. Utilise-le comme base principale pour répondre :";
            $systemContext .= $contextSnippet;
        }

        $systemContext .= "\n\nConsignes pédagogiques :\n";
        $systemContext .= "- Adapte toujours le ton et la charge de travail à l'humeur (humeur basse => explications courtes, rassurantes, étapes progressives).\n";
        $systemContext .= "- Si un test ou un examen approche, propose de te concentrer sur l'essentiel pour maximiser les points et suggère d'utiliser des questions de type examen.\n";
        $systemContext .= "- Si l'utilisateur mentionne un cours, un PDF ou une vidéo, propose clairement : résumé détaillé, version audio, carte mentale, quiz adaptatif, flashcards, fiches de révision.\n";
        $systemContext .= "- Quand il semble perdu ou pose des questions très basiques, ralentis le rythme, utilise des analogies simples et vérifie sa compréhension régulièrement.\n";
        $systemContext .= "- Quand il progresse vite et pose des questions avancées, augmente progressivement la difficulté et propose des défis supplémentaires.\n";
        $systemContext .= "- Ne parle jamais de ces règles méta, applique-les simplement dans tes réponses.";

        // Sanitize all strings for UTF-8 before API call
        $systemContext = $this->sanitizeUtf8($systemContext);
        $userMessage = $this->sanitizeUtf8($userMessage);

        return $this->callOpenRouter([
            ['role' => 'system', 'content' => $systemContext],
            ['role' => 'user', 'content' => $userMessage],
        ], 250);
    }

    private function summarizeText(string $text, ProfilApprentissage $profil): string
    {
        $vitesseLabel = $this->mapVitesseToLabel($profil->getVitesseApprentissage());
        $style = $vitesseLabel === 'lente' ? 'détaillé' : 'concis';
        $prompt = "Résume ce cours en style $style, avec des analogies simples si besoin (matières fortes: {$profil->getMatieresFortes()}):\n\n" . substr($text, 0, 800);
        
        // Sanitize prompt for UTF-8
        $prompt = $this->sanitizeUtf8($prompt);

        return $this->callOpenRouter([
            ['role' => 'user', 'content' => $prompt]
        ], 300);
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

    private function audioToText(string $audioBase64): ?string
    {
        // Audio transcription disabled - OpenAI removed
        return null;
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
        $prompt = "Tu es un générateur de quiz.\n"
            . "Crée exactement 5 questions QCM à partir du cours ci-dessous.\n"
            . "Retourne UNIQUEMENT un JSON valide, sans texte autour, format exact :\n"
            . "{\"quiz\":[{\"question\":\"...\",\"options\":[\"...\",\"...\",\"...\",\"...\"],\"correct\":0,\"explanation\":\"...\"}]}\n"
            . "Règles :\n"
            . "- correct doit être un index 0..3\n"
            . "- options doit contenir exactement 4 réponses\n"
            . "- questions courtes, niveau étudiant\n\n"
            . "Cours:\n" . substr($content, 0, 2200);

        $prompt = $this->sanitizeUtf8($prompt);

        $response = $this->callOpenRouter([
            ['role' => 'user', 'content' => $prompt]
        ], 900);

        if (strpos($response, 'Erreur') !== false || empty(trim($response))) {
            error_log('Quiz generation failed: ' . $response);
            return [];
        }

        $parsed = $this->extractJsonPayload($response);
        $questions = [];

        if (isset($parsed['quiz']) && is_array($parsed['quiz'])) {
            foreach ($parsed['quiz'] as $item) {
                $questionText = trim((string)($item['question'] ?? ''));
                $options = $item['options'] ?? [];
                $correct = isset($item['correct']) ? (int)$item['correct'] : -1;
                $explanation = trim((string)($item['explanation'] ?? ''));

                if ($questionText === '' || !is_array($options) || count($options) !== 4 || $correct < 0 || $correct > 3) {
                    continue;
                }

                $questions[] = [
                    'question' => $questionText,
                    'options' => array_values(array_map(fn($opt) => trim((string)$opt), $options)),
                    'correct' => $correct,
                    'explanation' => $explanation,
                ];
            }
        }

        if (!empty($questions)) {
            return array_slice($questions, 0, 5);
        }

        // Fallback parser (legacy pipe format)
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            if (!trim($line) || !str_contains($line, '|')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 6) {
                continue;
            }

            $correct = (int)$parts[5];
            if ($correct < 0 || $correct > 3) {
                $correct = 0;
            }

            $questions[] = [
                'question' => $parts[0],
                'options' => [$parts[1], $parts[2], $parts[3], $parts[4]],
                'correct' => $correct,
                'explanation' => $parts[6] ?? '',
            ];
        }

        return array_slice($questions, 0, 5);
    }

    private function generateAdaptiveFlashcards(string $content, ProfilApprentissage $profil): array
    {
        $prompt = "Tu es un générateur de flashcards.\n"
            . "Crée exactement 3 flashcards utiles à réviser à partir du cours.\n"
            . "Retourne UNIQUEMENT un JSON valide, sans texte autour, format exact :\n"
            . "{\"flashcards\":[{\"front\":\"Question courte\",\"back\":\"Réponse claire et utile\"}]}\n"
            . "Règles :\n"
            . "- front court\n"
            . "- back précis, 1 à 3 phrases\n"
            . "- pas de doublons\n\n"
            . "Cours:\n" . substr($content, 0, 2200);

        $prompt = $this->sanitizeUtf8($prompt);

        $response = $this->callOpenRouter([
            ['role' => 'user', 'content' => $prompt]
        ], 700);

        if (strpos($response, 'Erreur') !== false || empty(trim($response))) {
            error_log('Flashcards generation failed: ' . $response);
            return [];
        }

        $parsed = $this->extractJsonPayload($response);
        $cards = [];

        if (isset($parsed['flashcards']) && is_array($parsed['flashcards'])) {
            foreach ($parsed['flashcards'] as $item) {
                $front = trim((string)($item['front'] ?? ''));
                $back = trim((string)($item['back'] ?? ''));

                if ($front === '' || $back === '') {
                    continue;
                }

                $cards[] = [
                    'front' => $front,
                    'back' => $back,
                ];
            }
        }

        if (!empty($cards)) {
            return array_slice($cards, 0, 3);
        }

        // Fallback parser (front | back)
        $lines = explode("\n", $response);
        foreach ($lines as $line) {
            if (!trim($line) || !str_contains($line, '|')) {
                continue;
            }

            $parts = array_map('trim', explode('|', $line));
            if (count($parts) < 2) {
                continue;
            }

            $cards[] = [
                'front' => $parts[0],
                'back' => $parts[1],
            ];
        }

        return array_slice($cards, 0, 3);
    }

    private function extractJsonPayload(string $raw): array
    {
        $text = trim($raw);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
            $text = preg_replace('/\s*```$/', '', (string)$text);
            $text = trim((string)$text);
        }

        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $match)) {
            $decoded = json_decode($match[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
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

    private function analyzeAndUpdateProfile(ProfilApprentissage $profil, string $userMessage, string $response, int $learningDuration, ?string $responseFormat = null): void
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

        // Heuristic update for learning speed (vitesse_apprentissage)
        $currentSpeed = $profil->getVitesseApprentissage() ?? 5;
        if ($learningDuration > 180) { // long time to process -> probably slower
            $currentSpeed = max(1, $currentSpeed - 1);
        } elseif ($learningDuration < 45) { // very fast -> probably quicker learner
            $currentSpeed = min(10, $currentSpeed + 1);
        }
        $profil->setVitesseApprentissage($currentSpeed);

        // Update preferred format based on actual usage
        if ($responseFormat) {
            $currentFormat = $profil->getFormatPréféré();
            if ($currentFormat === null || $currentFormat !== $responseFormat) {
                $profil->setFormatPréféré($responseFormat);
            }
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