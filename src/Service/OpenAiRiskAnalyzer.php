<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;

final class OpenAiRiskAnalyzer
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL   = 'gpt-3.5-turbo';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $openAiApiKey,
    ) {}

    /**
     * Analyse un profil utilisateur et retourne le verdict IA (texte).
     */
    public function analyze(Utilisateur $user): string
    {
        $prompt = $this->buildPrompt($user);

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->openAiApiKey,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 30,
                'json' => [
                    'model'       => self::MODEL,
                    'messages'    => [
                        [
                            'role'    => 'system',
                            'content' => 'Tu es un expert en cybersécurité chargé de détecter les comptes utilisateurs suspects sur une plateforme de e-learning. Réponds toujours en français, de manière concise (3-5 phrases maximum). Termine par un verdict clair : SUSPECT, NEUTRE ou FIABLE.',
                        ],
                        [
                            'role'    => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'max_tokens'  => 300,
                    'temperature' => 0.3,
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 429) {
                throw new \RuntimeException(
                    'Limite de requêtes OpenAI atteinte (quota dépassé ou compte sans crédits). ' .
                    'Vérifiez votre plan sur platform.openai.com et réessayez dans quelques secondes.'
                );
            }

            if ($statusCode === 401) {
                throw new \RuntimeException(
                    'Clé API OpenAI invalide ou expirée. Vérifiez OPENAI_API_KEY dans votre fichier .env.'
                );
            }

            if ($statusCode >= 500) {
                throw new \RuntimeException(
                    'Les serveurs OpenAI rencontrent une erreur temporaire (HTTP ' . $statusCode . '). Réessayez plus tard.'
                );
            }

            $data = $response->toArray();

            return $data['choices'][0]['message']['content'] ?? 'Analyse impossible : réponse vide de OpenAI.';

        } catch (\RuntimeException $e) {
            // Nos erreurs métier : on les remonte directement
            throw $e;
        } catch (\Throwable $e) {
            // Détecter le 429 dans le message brut si toArray() a déjà lancé l'exception
            if (str_contains($e->getMessage(), '429')) {
                throw new \RuntimeException(
                    'Limite de requêtes OpenAI atteinte (429 Too Many Requests). ' .
                    'Votre compte est peut-être sur le tier gratuit (3 req/min). Attendez 60 secondes et réessayez.'
                );
            }

            if (str_contains($e->getMessage(), '401')) {
                throw new \RuntimeException(
                    'Clé API OpenAI invalide (401). Vérifiez OPENAI_API_KEY dans .env.'
                );
            }

            throw new \RuntimeException('Erreur lors de l\'appel OpenAI : ' . $e->getMessage());
        }
    }

    private function buildPrompt(Utilisateur $user): string
    {
        $dateInscription = $user->getDateInscription()
            ? $user->getDateInscription()->format('d/m/Y')
            : 'inconnue';

        $lastLogin = $user->getLastLogin()
            ? $user->getLastLogin()->format('d/m/Y H:i')
            : 'jamais';

        return <<<PROMPT
Analyse ce profil utilisateur et dis-moi s'il semble suspect :

Nom         : {$user->getNom()} {$user->getPrenom()}
Email       : {$user->getEmail()}
Rôle        : {$user->getRole()}
Date d'inscription : {$dateInscription}
Dernière connexion : {$lastLogin}
Tentatives de connexion échouées : {$user->getLoginAttempts()}
IP d'inscription   : {$user->getRegistrationIp()}
Trust Score (règles internes) : {$user->getTrustScore()}%
Risk Level  : {$user->getRiskLevel()}
Doublon IP  : {$this->boolLabel($user->isFlaggedDuplicate())}
Photo de profil : {$this->boolLabel((bool) $user->getPdpUrl())}
Statut du compte : {$user->getStatus()}
PROMPT;
    }

    private function boolLabel(bool $v): string
    {
        return $v ? 'Oui' : 'Non';
    }
}
