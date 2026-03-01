<?php

namespace App\Controller;

use App\Entity\Motivation;
use App\Entity\Programme;
use App\Entity\Tache;
use App\Enum\Etat;
use App\Enum\Statutobj;
use App\Form\TacheType;
use App\Service\ObjectifStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/programme', name: 'front_programme_')]
class ProgrammeController extends AbstractController
{
    private ObjectifStatusService $objectifStatusService;

    public function __construct(ObjectifStatusService $objectifStatusService)
    {
        $this->objectifStatusService = $objectifStatusService;
    }

    private function updateProgrammeStats(Programme $programme, EntityManagerInterface $entityManager): void
    {
        $taches = $programme->getTache()->toArray();
        usort($taches, fn($a, $b) => $a->getOrdre() <=> $b->getOrdre());
        $total = count($taches);

        if ($total === 0) {
            $programme->setScorePourcentage(0);
            $programme->setMeilleureMedaille(null);
            if ($programme->getObjectif()) {
                $programme->getObjectif()->setStatut(Statutobj::Abandonner);
            }
            $entityManager->flush();
            return;
        }

        $realisees = 0;

        foreach ($taches as $tache) {
            if (in_array($tache->getEtat()->value, [Etat::realisee->value])) {
                $realisees++;
            }
        }

        $score = (int) round(($realisees / $total) * 100);

        // Attribution médaille
        $meilleureMedaille = null;
        if ($score >= 90) {
            $meilleureMedaille = \App\Enum\Medaille::Or;
        } elseif ($score >= 60) {
            $meilleureMedaille = \App\Enum\Medaille::Argent;
        } elseif ($score >= 30) {
            $meilleureMedaille = \App\Enum\Medaille::Bronze;
        }

        $programme->setScorePourcentage($score);
        $programme->setMeilleureMedaille($meilleureMedaille);
        $entityManager->flush();

        if ($programme->getObjectif()) {
            $this->objectifStatusService->updateStatusFromProgrammeScore($programme->getObjectif());
        }

        // Génération message motivant avec Ollama
        try {
            $ollamaUrl = 'http://127.0.0.1:11434/api/generate';
            $objectifTitre = $programme->getObjectif()?->getTitre() ?? 'ton objectif';
            
            $prompt = "Tu es un mentor encourageant pour des étudiants. L'étudiant a un score de {$score}% sur son objectif '{$objectifTitre}'. 
Génère UN SEUL message motivant de 2-3 phrases maximum, adapté à ce score :
- Si score < 30% : encourage à commencer et à ne pas abandonner
- Si score 30-60% : félicite les efforts et encourage à continuer
- Si score 60-90% : félicite chaudement et motive pour la dernière ligne droite
- Si score > 90% : grande félicitation et fierté

Ton message doit être personnel, chaleureux et motivant. Retourne UNIQUEMENT le message, sans guillemets, sans préfixe.";

            $response = @file_get_contents($ollamaUrl, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode([
                        'model' => 'llama3.1:8b',
                        'prompt' => $prompt,
                        'stream' => false,
                        'options' => ['temperature' => 0.8]
                    ]),
                    'timeout' => 30
                ]
            ]));

            if ($response !== false) {
                $data = json_decode($response, true);
                $messageMotivant = trim($data['response'] ?? '');
                $messageMotivant = preg_replace('/^["\']+|["\']+$/', '', $messageMotivant);
                $messageMotivant = trim($messageMotivant);
            }
        } catch (\Exception $e) {
            $messageMotivant = '';
        }

        // Fallback si Ollama échoue
        if (empty($messageMotivant)) {
            $messageMotivant = match(true) {
                $score < 30 => "Chaque grand voyage commence par un premier pas. Tu as {$score}% — c'est un début ! Continue, tu es sur la bonne voie.",
                $score < 60 => "Bravo pour tes efforts ! {$score}% de réalisé, tu progresses bien. Garde ce rythme, le succès approche !",
                $score < 90 => "Excellent travail ! {$score}% accompli, tu es presque au bout ! Dernière ligne droite, tu vas y arriver !",
                default => "🎉 Incroyable ! {$score}% de réussite ! Tu as tout donné et ça paie. Félicitations, tu peux être fier de toi !"
            };
        }

        // Sauvegarde message motivant avec fuseau horaire correct
        $motivation = new Motivation();
        $motivation->setMessagemotivant($messageMotivant);
        $motivation->setDategeneratiomm(new \DateTime('now', new \DateTimeZone('Africa/Tunis')));
        $motivation->setProgramme($programme);

        $entityManager->persist($motivation);
        $entityManager->flush();
    }

    #[Route('/{id}', name: 'show', methods: ['GET', 'POST'])]
public function show(
    Request $request,
    Programme $programme,
    EntityManagerInterface $entityManager
): Response {
    $tache = new Tache();
    $tache->setProgramme($programme);

    // IMPORTANT : passe le programme au formulaire
    $formTache = $this->createForm(TacheType::class, $tache, [
        'programme' => $programme,
    ]);

    $formTache->handleRequest($request);

    if ($formTache->isSubmitted() && $formTache->isValid()) {
        $entityManager->persist($tache);
        $entityManager->flush();

        $this->updateProgrammeStats($programme, $entityManager);

        $this->addFlash('success', 'Tâche ajoutée avec succès !');
        return $this->redirectToRoute('front_programme_show', ['id' => $programme->getId()]);
    }

    return $this->render('front/programme_show.html.twig', [
        'programme' => $programme,
        'formTache' => $formTache->createView(),
    ]);
}

    #[Route('/{id}/generate', name: 'generate_ai', methods: ['POST'])]
    public function generateProgramme(
        Programme $programme,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        if (!$this->isCsrfTokenValid('generate_ai_' . $programme->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('front_programme_show', ['id' => $programme->getId()]);
        }

        $objectif = $programme->getObjectif();
        if (!$objectif) {
            $this->addFlash('danger', 'Aucun objectif.');
            return $this->redirectToRoute('front_programme_show', ['id' => $programme->getId()]);
        }

        $description = $objectif->getDescription() ?? 'Objectif sans description';

        try {
            $ollamaUrl = 'http://127.0.0.1:11434/api/generate';

            $prompt = "Objectif : $description.  
Génère 6 à 10 tâches progressives et concrètes.  
Retourne UNIQUEMENT un JSON valide : {\"taches\": [{\"ordre\":1,\"titre\":\"...\",\"description\":\"...\",\"etat\":\"Abandonner\"}]}";

            $response = @file_get_contents($ollamaUrl, false, stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\n",
                    'content' => json_encode([
                        'model' => 'llama3.1:8b',
                        'prompt' => $prompt,
                        'stream' => false,
                        'options' => ['temperature' => 0.7]
                    ]),
                    'timeout' => 60
                ]
            ]));

            if ($response === false) {
                throw new \Exception('Impossible de contacter Ollama.');
            }

            $data = json_decode($response, true);
            $text = trim($data['response'] ?? '');

            // Nettoyage JSON
            $text = preg_replace('/^```json\s*/i', '', $text);
            $text = preg_replace('/^```/i', '', $text);
            $text = preg_replace('/```$/i', '', $text);
            $text = preg_replace('/^json\s*/i', '', $text);
            $text = trim($text);

            if (strpos($text, '{') === 0 || strpos($text, '[') === 0) {
                $json = json_decode($text, true);
            } else {
                $start = strpos($text, '{');
                $end = strrpos($text, '}');
                if ($start !== false && $end !== false) {
                    $jsonText = substr($text, $start, $end - $start + 1);
                    $json = json_decode($jsonText, true);
                } else {
                    $json = null;
                }
            }

            if (json_last_error() !== JSON_ERROR_NONE || !isset($json['taches']) || empty($json['taches'])) {
                $json = [
                    'taches' => [
                        ['ordre' => 1, 'titre' => 'Analyser objectif', 'description' => 'Lire la description attentivement', 'etat' => 'Abandonner'],
                        ['ordre' => 2, 'titre' => 'Decouper en etapes', 'description' => 'Creer une liste de 5 actions', 'etat' => 'Abandonner'],
                        ['ordre' => 3, 'titre' => 'Planifier le temps', 'description' => 'Bloquer 1h par jour dans agenda', 'etat' => 'Abandonner'],
                        ['ordre' => 4, 'titre' => 'Commencer maintenant', 'description' => 'Faire la premiere action immediatement', 'etat' => 'Abandonner'],
                        ['ordre' => 5, 'titre' => 'Suivre les progres', 'description' => 'Noter chaque avancement quotidien', 'etat' => 'Abandonner']
                    ]
                ];
            }

            foreach ($programme->getTache() as $old) {
                $em->remove($old);
            }

            foreach ($json['taches'] as $t) {
                $tache = new Tache();
                $tache->setOrdre((int) ($t['ordre'] ?? 1));
                $tache->setTitre($t['titre'] ?? 'Tâche sans titre');
                $tache->setDescription($t['description'] ?? 'Description manquante');
                $tache->setEtat(Etat::Abandonner);
                $tache->setProgramme($programme);
                $em->persist($tache);
            }

            $programme->setDategeneration(new \DateTime());
            $em->flush();

            $this->updateProgrammeStats($programme, $em);

            $this->addFlash('success', 'Programme généré par IA locale (Ollama) !');

        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('front_programme_show', ['id' => $programme->getId()]);
    }
}