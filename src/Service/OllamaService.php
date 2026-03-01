<?php
// src/Service/OllamaService.php
namespace App\Service;

use Psr\Log\LoggerInterface;

class OllamaService
{
    private LoggerInterface $logger;
    private string $model;

    public function __construct(
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->model = 'mistral:7b'; // Modèle qui fonctionne
    }

   /** @return array{success: bool, response: string} */
public function sendMessage(string $message): array
    {
        try {
            $this->logger->info('🔍 Envoi à Ollama', ['message' => $message]);

            // Construire le prompt comme en ligne de commande
            $prompt = $this->buildPrompt($message);
            
            // Préparer la commande (la même qui a fonctionné)
            $command = sprintf(
                'ollama run %s "%s" 2>&1',
                escapeshellarg($this->model),
                escapeshellarg($prompt)
            );
            
            $this->logger->info('🔍 Commande', ['command' => $command]);
            
            // Exécuter la commande
            $output = shell_exec($command);

if ($output === null || $output === false) {
    throw new \Exception("La commande Ollama a échoué");
}
            
            $this->logger->info('✅ Réponse reçue', ['response' => substr($output, 0, 200)]);

return [
    'success' => true,
    'response' => trim($output)
];

        } catch (\Exception $e) {
            $this->logger->error('❌ Erreur Ollama', [
                'error' => $e->getMessage()
            ]);
            
            // Mode démo en cas d'erreur
            return $this->getDemoResponse($message);
        }
    }

    private function buildPrompt(string $message): string
    {
        $context = "
        Tu es un assistant pédagogique expert pour MentorAI.
        
        Contexte spécifique:
        - Les alertes concernent les élèves en difficulté
        - Les prédictions portent sur la réussite scolaire
        - Les recommandations doivent être concrètes et applicables
        - Les plans d'action sont structurés (objectifs, ressources, étapes)
        
        Réponds en français de manière professionnelle et utile.
        ";
        
        return $context . "\n\nQuestion: " . $message . "\n\nRéponse:";
    }

    /** @return array{success: bool, response: string} */
private function getDemoResponse(string $message): array
    {
        $messageLower = strtolower($message);
        
        if (str_contains($messageLower, 'alerte')) {
            return [
                'success' => true,
                'response' => "🔔 Alerte pédagogique : L'élève montre des signes de difficulté. Recommandation : entretien avec les parents et soutien scolaire personnalisé."
            ];
        }
        if (str_contains($messageLower, 'prédiction') || str_contains($messageLower, 'réussite')) {
            return [
                'success' => true,
                'response' => "📊 Prédiction : 85% de chances de réussite avec un suivi régulier et un accompagnement personnalisé."
            ];
        }
        if (str_contains($messageLower, 'participation')) {
            return [
                'success' => true,
                'response' => "💡 Recommandations pour améliorer la participation :\n1. Activités interactives\n2. Feedback positif régulier\n3. Jeux éducatifs\n4. Travail en groupe\n5. Valoriser les efforts"
            ];
        }
        if (str_contains($messageLower, 'plan')) {
            return [
                'success' => true,
                'response' => "📝 Plan pédagogique :\n1. Objectifs d'apprentissage\n2. Ressources nécessaires\n3. Déroulement des activités\n4. Méthodes d'évaluation\n5. Adaptation selon les besoins"
            ];
        }
        
        return [
            'success' => true,
            'response' => "Je suis votre assistant pédagogique. Comment puis-je vous aider ? Posez-moi des questions sur l'enseignement, la pédagogie, ou la gestion de classe."
        ];
    }
}