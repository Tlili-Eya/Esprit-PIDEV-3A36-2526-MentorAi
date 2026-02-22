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
     * 
     * @param string $text Le texte à analyser
     * @return array Résultat de l'analyse avec émotion, scores et recommandation
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
            'error' => null
        ];

        try {
            // Validation du texte
            if (empty(trim($text))) {
                return array_merge($defaultResult, [
                    'recommendation' => 'Texte vide - Aucune analyse possible'
                ]);
            }

            // Préparer la requête pour Azure
            $url = $this->azureEndpoint . '/text/analytics/v3.1/sentiment';
            
            $requestData = [
                'documents' => [
                    [
                        'id' => '1',
                        'language' => 'fr', // Français
                        'text' => substr($text, 0, 5000) // Limite Azure : 5000 caractères
                    ]
                ]
            ];

            // Envoyer la requête à Azure
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->azureApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $requestData,
                'timeout' => 10
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                $this->logger->warning('Azure API returned non-200 status', [
                    'status_code' => $statusCode,
                    'response' => $response->getContent(false)
                ]);
                
                return array_merge($defaultResult, [
                    'recommendation' => 'Service d\'analyse temporairement indisponible',
                    'error' => "HTTP $statusCode"
                ]);
            }

            $data = $response->toArray();

            // Vérifier la structure de la réponse
            if (!isset($data['documents'][0]['sentiment'])) {
                $this->logger->error('Invalid Azure API response structure', ['response' => $data]);
                return array_merge($defaultResult, [
                    'recommendation' => 'Réponse API invalide',
                    'error' => 'Invalid response structure'
                ]);
            }

            $document = $data['documents'][0];
            $sentiment = strtoupper($document['sentiment']);
            $confidenceScores = $document['confidenceScores'];

            // Convertir les scores Azure en format utilisable
            $scores = [
                'positive' => $confidenceScores['positive'] ?? 0,
                'neutral' => $confidenceScores['neutral'] ?? 0,
                'negative' => $confidenceScores['negative'] ?? 0
            ];

            // Déterminer l'émotion principale et l'emoji
            $emotionData = $this->determineEmotionAndEmoji($sentiment, $scores);

            // Générer une recommandation
            $recommendation = $this->generateRecommendation($sentiment, $scores);

            // Déterminer le niveau de confiance
            $confidence = $this->determineConfidence($scores);

            return [
                'emotion' => $emotionData['emotion'],
                'emoji' => $emotionData['emoji'],
                'scores' => $scores,
                'recommendation' => $recommendation,
                'confidence' => $confidence,
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
     * Génère une recommandation basée sur l'analyse
     */
    private function generateRecommendation(string $sentiment, array $scores): string
    {
        $negativeScore = $scores['negative'] ?? 0;
        $positiveScore = $scores['positive'] ?? 0;

        switch ($sentiment) {
            case 'NEGATIVE':
                if ($negativeScore > 0.8) {
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
                return '🤔 Sentiment mitigé - Analyser les points positifs et négatifs';

            case 'NEUTRAL':
            default:
                return 'ℹ️ Feedback informatif - Réponse standard appropriée';
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