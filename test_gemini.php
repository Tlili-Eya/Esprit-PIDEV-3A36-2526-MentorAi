<?php

// Test d'OpenRouter API

$apiKey = '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';
$url = 'https://openrouter.ai/api/v1/chat/completions';

$data = [
    'model' => 'openrouter/auto', // Laisse OpenRouter choisir le meilleur modèle
    'messages' => [
        ['role' => 'user', 'content' => 'Explique brièvement la photosynthèse.']
    ],
    'temperature' => 0.9,
    'max_tokens' => 250,
];

echo "⏱️  Test OpenRouter API...\n\n";
$startTime = microtime(true);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $apiKey,
    'HTTP-Referer: https://mentorai.local',
    'X-Title: MentorAI',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$endTime = microtime(true);
$duration = round(($endTime - $startTime) * 1000);

echo "HTTP Code: $httpCode\n";
echo "⏱️  Temps de réponse: {$duration}ms\n\n";

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if (isset($result['choices'][0]['message']['content'])) {
        echo "✅ OpenRouter fonctionne!\n";
        echo "Réponse: " . $result['choices'][0]['message']['content'] . "\n\n";
        echo "🚀 Configuration:\n";
        echo "   • API: OpenRouter\n";
        echo "   • Modèle: openrouter/auto (sélection automatique)\n";
        echo "   • MaxTokens: 250 (réponses rapides)\n";
        echo "   • Pas de limite de quota gratuites\n";
    } else {
        echo "❌ Format inattendu\n";
        echo json_encode($result, JSON_PRETTY_PRINT) . "\n";
    }
} else {
    echo "❌ Erreur\n";
    echo "Réponse: $response\n";
}




