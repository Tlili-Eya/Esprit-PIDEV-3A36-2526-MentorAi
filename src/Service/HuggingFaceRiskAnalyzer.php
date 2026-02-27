<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Analyse de risque utilisateur via Groq API (gratuit, sans carte bancaire).
 *
 * Groq = infrastructure ultra-rapide pour modèles open-source.
 * Tier gratuit : 30 req/min, 6000 req/jour.
 * Clé gratuite sur : console.groq.com
 *
 * Le nom de la classe reste HuggingFaceRiskAnalyzer pour ne pas casser
 * les services.yaml et le controller existants.
 */
final class HuggingFaceRiskAnalyzer
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const MODEL   = 'llama-3.3-70b-versatile';  // Llama 3.3 70B — gratuit sur Groq

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $huggingFaceApiKey,   // maintenant = clé Groq (dans .env HUGGINGFACE_API_KEY)
    ) {}

    /**
     * Analyse un profil utilisateur via Groq et retourne le verdict texte.
     *
     * @throws \RuntimeException si l'API est indisponible ou retourne une erreur.
     */
    public function analyze(Utilisateur $user): string
    {
        $prompt = $this->buildPrompt($user);

        $response = $this->httpClient->request('POST', self::API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->huggingFaceApiKey,
                'Content-Type'  => 'application/json',
            ],
            'timeout' => 30,
            'json' => [
                'model'       => self::MODEL,
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => 'Tu es un expert en cybersécurité chargé de détecter les comptes suspects sur une plateforme e-learning. Réponds toujours en français, de manière concise (4-6 phrases). Termine obligatoirement par : VERDICT: SUSPECT, VERDICT: NEUTRE, ou VERDICT: FIABLE.',
                    ],
                    [
                        'role'    => 'user',
                        'content' => $prompt,
                    ],
                ],
                'max_tokens'  => 400,
                'temperature' => 0.3,
                'stream'      => false,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        $rawContent = $response->getContent(false);
        $data       = json_decode($rawContent, true);

        // ── Codes d'erreur HTTP ──────────────────────────────────────────────
        if ($statusCode === 401) {
            throw new \RuntimeException(
                "Clé API Groq invalide (401).\n" .
                "Vérifiez HUGGINGFACE_API_KEY dans .env (c'est maintenant votre clé Groq).\n" .
                "Obtenez une clé gratuite sur : console.groq.com"
            );
        }

        if ($statusCode === 429) {
            throw new \RuntimeException(
                "Limite Groq atteinte (429).\n" .
                "Tier gratuit : 30 requêtes/min, 6000/jour. Attendez quelques secondes."
            );
        }

        if ($statusCode >= 400) {
            $errMsg = $data['error']['message'] ?? $data['error'] ?? $rawContent;
            throw new \RuntimeException(
                "Groq API erreur {$statusCode} :\n" . substr(is_array($errMsg) ? json_encode($errMsg) : $errMsg, 0, 400)
            );
        }

        // ── Réponse JSON invalide ────────────────────────────────────────────
        if ($data === null) {
            throw new \RuntimeException(
                "Réponse non-JSON reçue.\nContenu : " . substr($rawContent, 0, 300)
            );
        }

        // ── Extraire le texte — format OpenAI chat completions ───────────────
        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }

        throw new \RuntimeException(
            "Format de réponse inattendu.\nContenu : " . substr(json_encode($data), 0, 400)
        );
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
