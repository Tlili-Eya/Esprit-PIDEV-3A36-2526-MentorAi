<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class AzureEmotionAnalysisService
{
    private string $azureEndpoint;
    private string $azureApiKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        string $azureEndpoint,
        string $azureApiKey,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->azureEndpoint = rtrim($azureEndpoint, '/');
        $this->azureApiKey = $azureApiKey;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Analyse l'émotion d'un texte via Azure Text Analytics
     * ✨ ENHANCED: Détecte aussi les problèmes techniques et insights émotionnels profonds
     * 
     * @param string $text Le texte à analyser
     * @return array Résultat de l'analyse avec émotion, scores, recommandation, problèmes techniques et insights
     */
    public function analyzeEmotion(string $text): array
    {
        // Résultat par défaut en cas d'erreur
        $defaultResult = [
            'emotion' => 'NEUTRAL',
            'emoji' => '😐',
            'scores' => [
                'positive' => 0.33,
                'neutral' => 0.34,
                'negative' => 0.33
            ],
            'recommendation' => 'Analyse non disponible',
            'confidence' => 'low',
            'technical_issues' => [],
            'key_phrases' => [],
            'emotional_insights' => 'Aucun insight disponible',
            'error' => null
        ];

        try {
            // Validation du texte
            if (empty(trim($text))) {
                return array_merge($defaultResult, [
                    'recommendation' => 'Texte vide - Aucune analyse possible'
                ]);
            }

            // ✨ ANALYSE PARALLÈLE : Sentiment + Key Phrases
            $sentimentResult = $this->analyzeSentiment($text);
            $keyPhrasesResult = $this->extractKeyPhrases($text);
            
            // Détecter les problèmes techniques
            $technicalIssues = $this->detectTechnicalIssues($text, $keyPhrasesResult);
            
            // Générer des insights émotionnels profonds
            $emotionalInsights = $this->generateEmotionalInsights(
                $sentimentResult['sentiment'],
                $sentimentResult['scores'],
                $keyPhrasesResult,
                $technicalIssues
            );

            // Déterminer l'émotion principale et l'emoji
            $emotionData = $this->determineEmotionAndEmoji($sentimentResult['sentiment'], $sentimentResult['scores']);

            // Générer une recommandation enrichie
            $recommendation = $this->generateEnhancedRecommendation(
                $sentimentResult['sentiment'],
                $sentimentResult['scores'],
                $technicalIssues
            );

            // Déterminer le niveau de confiance
            $confidence = $this->determineConfidence($sentimentResult['scores']);

            return [
                'emotion' => $emotionData['emotion'],
                'emoji' => $emotionData['emoji'],
                'scores' => $sentimentResult['scores'],
                'recommendation' => $recommendation,
                'confidence' => $confidence,
                'technical_issues' => $technicalIssues,
                'key_phrases' => $keyPhrasesResult,
                'emotional_insights' => $emotionalInsights,
                'error' => null
            ];

        } catch (\Exception $e) {
            $this->logger->error('Azure Emotion Analysis failed', [
                'error' => $e->getMessage(),
                'text_length' => strlen($text)
            ]);

            return array_merge($defaultResult, [
                'recommendation' => 'Erreur lors de l\'analyse - Traitement manuel recommandé',
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * ✨ NEW: Analyse le sentiment via Azure
     */
    private function analyzeSentiment(string $text): array
    {
        $url = $this->azureEndpoint . '/text/analytics/v3.1/sentiment';
        
        $requestData = [
            'documents' => [
                [
                    'id' => '1',
                    'language' => 'fr',
                    'text' => substr($text, 0, 5000)
                ]
            ]
        ];

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Ocp-Apim-Subscription-Key' => $this->azureApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => $requestData,
            'timeout' => 10
        ]);

        $data = $response->toArray();
        $document = $data['documents'][0];

        return [
            'sentiment' => strtoupper($document['sentiment']),
            'scores' => [
                'positive' => $document['confidenceScores']['positive'] ?? 0,
                'neutral' => $document['confidenceScores']['neutral'] ?? 0,
                'negative' => $document['confidenceScores']['negative'] ?? 0
            ]
        ];
    }

    /**
     * ✨ NEW: Extrait les phrases clés via Azure
     */
    private function extractKeyPhrases(string $text): array
    {
        try {
            $url = $this->azureEndpoint . '/text/analytics/v3.1/keyPhrases';
            
            $requestData = [
                'documents' => [
                    [
                        'id' => '1',
                        'language' => 'fr',
                        'text' => substr($text, 0, 5000)
                    ]
                ]
            ];

            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->azureApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
                'timeout' => 10
            ]);

            $data = $response->toArray();
            return $data['documents'][0]['keyPhrases'] ?? [];
            
        } catch (\Exception $e) {
            $this->logger->warning('Key phrase extraction failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * ✨ NEW: Détecte les problèmes techniques dans le texte
     */
    private function detectTechnicalIssues(string $text, array $keyPhrases): array
    {
        $issues = [];
        $textLower = mb_strtolower($text);
        
        // Mots-clés techniques à détecter
        $technicalKeywords = [
            'bug' => ['type' => 'Bug', 'priority' => 'high', 'icon' => '🐛'],
            'erreur' => ['type' => 'Erreur', 'priority' => 'high', 'icon' => '❌'],
            'problème' => ['type' => 'Problème', 'priority' => 'medium', 'icon' => '⚠️'],
            'crash' => ['type' => 'Crash', 'priority' => 'critical', 'icon' => '💥'],
            'lent' => ['type' => 'Performance', 'priority' => 'medium', 'icon' => '🐌'],
            'lenteur' => ['type' => 'Performance', 'priority' => 'medium', 'icon' => '🐌'],
            'ne fonctionne pas' => ['type' => 'Dysfonctionnement', 'priority' => 'high', 'icon' => '🚫'],
            'ne marche pas' => ['type' => 'Dysfonctionnement', 'priority' => 'high', 'icon' => '🚫'],
            'bloqué' => ['type' => 'Blocage', 'priority' => 'high', 'icon' => '🔒'],
            'impossible' => ['type' => 'Blocage', 'priority' => 'high', 'icon' => '🚧'],
            'connexion' => ['type' => 'Connexion', 'priority' => 'medium', 'icon' => '🔌'],
            'chargement' => ['type' => 'Chargement', 'priority' => 'low', 'icon' => '⏳'],
            'affichage' => ['type' => 'Interface', 'priority' => 'low', 'icon' => '🖥️'],
        ];

        foreach ($technicalKeywords as $keyword => $details) {
            if (strpos($textLower, $keyword) !== false) {
                $issues[] = [
                    'keyword' => $keyword,
                    'type' => $details['type'],
                    'priority' => $details['priority'],
                    'icon' => $details['icon']
                ];
            }
        }

        // Analyser les phrases clés pour détecter des problèmes techniques
        foreach ($keyPhrases as $phrase) {
            $phraseLower = mb_strtolower($phrase);
            if (preg_match('/(erreur|bug|problème|crash|défaut|panne)/i', $phraseLower)) {
                $issues[] = [
                    'keyword' => $phrase,
                    'type' => 'Problème identifié',
                    'priority' => 'medium',
                    'icon' => '🔍'
                ];
            }
        }

        // Dédupliquer
        return array_values(array_unique($issues, SORT_REGULAR));
    }

    /**
     * ✨ NEW: Génère des insights émotionnels profonds
     */
    private function generateEmotionalInsights(string $sentiment, array $scores, array $keyPhrases, array $technicalIssues): string
    {
        $insights = [];
        
        // Analyse du sentiment
        $negativeScore = $scores['negative'];
        $positiveScore = $scores['positive'];
        
        if ($negativeScore > 0.7) {
            $insights[] = "L'utilisateur exprime une forte frustration";
            if (count($technicalIssues) > 0) {
                $insights[] = "Cette frustration est liée à des problèmes techniques concrets";
            }
        } elseif ($negativeScore > 0.5) {
            $insights[] = "L'utilisateur est insatisfait mais reste constructif";
        }
        
        if ($positiveScore > 0.7) {
            $insights[] = "L'utilisateur est très satisfait de l'expérience";
        } elseif ($positiveScore > 0.5) {
            $insights[] = "L'utilisateur apprécie certains aspects du service";
        }
        
        // Analyse des problèmes techniques
        if (count($technicalIssues) > 2) {
            $insights[] = "Plusieurs problèmes techniques mentionnés - nécessite une attention immédiate";
        } elseif (count($technicalIssues) > 0) {
            $highPriority = array_filter($technicalIssues, fn($i) => $i['priority'] === 'high' || $i['priority'] === 'critical');
            if (count($highPriority) > 0) {
                $insights[] = "Problème technique critique détecté";
            }
        }
        
        // Analyse des phrases clés
        if (count($keyPhrases) > 5) {
            $insights[] = "Feedback détaillé avec plusieurs points spécifiques";
        }
        
        return !empty($insights) ? implode('. ', $insights) . '.' : 'Feedback standard sans particularité notable.';
    }

    /**
     * ✨ ENHANCED: Génère une recommandation enrichie avec détection technique
     */
    private function generateEnhancedRecommendation(string $sentiment, array $scores, array $technicalIssues): string
    {
        $negativeScore = $scores['negative'] ?? 0;
        $positiveScore = $scores['positive'] ?? 0;
        
        // Prioriser les problèmes techniques critiques
        $criticalIssues = array_filter($technicalIssues, fn($i) => $i['priority'] === 'critical');
        $highIssues = array_filter($technicalIssues, fn($i) => $i['priority'] === 'high');
        
        if (count($criticalIssues) > 0) {
            return '🚨 URGENT : Problème technique critique détecté - Escalade immédiate requise !';
        }
        
        if (count($highIssues) > 0 && $negativeScore > 0.6) {
            return '⚠️ Problème technique + utilisateur mécontent - Traitement prioritaire avec équipe technique';
        }

        switch ($sentiment) {
            case 'NEGATIVE':
                if (count($technicalIssues) > 0) {
                    return '⚠️ Problème technique identifié - Transférer à l\'équipe technique';
                } elseif ($negativeScore > 0.8) {
                    return '⚠️ Utilisateur très mécontent - Traitement prioritaire !';
                } elseif ($negativeScore > 0.6) {
                    return '⚠️ Utilisateur mécontent - Réponse rapide recommandée';
                } else {
                    return 'Feedback négatif - Traitement avec attention';
                }

            case 'POSITIVE':
                if ($positiveScore > 0.8) {
                    return '✅ Utilisateur très satisfait - Poursuivre dans cette direction';
                } else {
                    return '✅ Feedback positif - Utilisateur globalement satisfait';
                }

            case 'MIXED':
                if (count($technicalIssues) > 0) {
                    return '🤔 Sentiment mitigé avec problèmes techniques - Analyser et résoudre les points bloquants';
                }
                return '🤔 Sentiment mitigé - Analyser les points positifs et négatifs';

            case 'NEUTRAL':
            default:
                if (count($technicalIssues) > 0) {
                    return 'ℹ️ Feedback informatif avec mention technique - Vérifier les points soulevés';
                }
                return 'ℹ️ Feedback informatif - Réponse standard appropriée';
        }
    }

    /**
     * Détermine l'émotion et l'emoji basés sur le sentiment Azure
     */
    private function determineEmotionAndEmoji(string $sentiment, array $scores): array
    {
        switch ($sentiment) {
            case 'POSITIVE':
                return [
                    'emotion' => 'POSITIVE',
                    'emoji' => '😊'
                ];
            case 'NEGATIVE':
                return [
                    'emotion' => 'NEGATIVE',
                    'emoji' => '😞'
                ];
            case 'NEUTRAL':
                return [
                    'emotion' => 'NEUTRAL',
                    'emoji' => '😐'
                ];
            case 'MIXED':
                return [
                    'emotion' => 'MIXED',
                    'emoji' => '🤔'
                ];
            default:
                return [
                    'emotion' => 'NEUTRAL',
                    'emoji' => '😐'
                ];
        }
    }

    /**
     * Détermine le niveau de confiance de l'analyse
     */
    private function determineConfidence(array $scores): string
    {
        $maxScore = max($scores);
        
        if ($maxScore > 0.8) {
            return 'high';
        } elseif ($maxScore > 0.6) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Analyse plusieurs textes en lot (pour optimiser les appels API)
     */
    public function analyzeBatch(array $texts): array
    {
        $results = [];
        
        // Pour éviter de dépasser les quotas, on limite à 10 textes max
        $limitedTexts = array_slice($texts, 0, 10);
        
        foreach ($limitedTexts as $index => $text) {
            $results[$index] = $this->analyzeEmotion($text);
            
            // Petite pause entre les appels pour respecter les limites de taux
            if ($index < count($limitedTexts) - 1) {
                usleep(100000); // 0.1 seconde
            }
        }
        
        return $results;
    }
}