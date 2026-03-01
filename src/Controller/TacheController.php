<?php

namespace App\Controller;

use App\Entity\Programme;
use App\Entity\Tache;
use App\Enum\Etat;
use App\Form\TacheType;
use App\Service\ObjectifStatusService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/tache', name: 'front_tache_')]
class TacheController extends AbstractController
{
    private ObjectifStatusService $objectifStatusService;

    public function __construct(ObjectifStatusService $objectifStatusService)
    {
        $this->objectifStatusService = $objectifStatusService;
    }

    // 1. Afficher une tâche (Voir)
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Tache $tache): Response
    {
        return $this->render('front/tache_show.html.twig', [
            'tache' => $tache,
        ]);
    }

    // 2. Modifier une tâche
    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Tache $tache,
        EntityManagerInterface $entityManager
    ): Response {
        $form = $this->createForm(TacheType::class, $tache);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Mise à jour du score, médaille et statut objectif
            if ($programme = $tache->getProgramme()) {
                $this->updateProgrammeStats($programme, $entityManager);
            }

            $this->addFlash('success', 'Tâche modifiée avec succès !');
            return $this->redirectToRoute('front_programme_show', [
                'id' => $tache->getProgramme()->getId()
            ]);
        }

        return $this->render('front/tache_edit.html.twig', [
            'tache' => $tache,
            'form'  => $form->createView(),
        ]);
    }

    // 3. Supprimer une tâche
    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Tache $tache, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$tache->getId(), (string) $request->request->get('_token'))) {
            $programmeId = $tache->getProgramme()->getId();
            $entityManager->remove($tache);
            $entityManager->flush();

            // Mise à jour du score, médaille et statut objectif après suppression
            if ($programme = $tache->getProgramme()) {
                $this->updateProgrammeStats($programme, $entityManager);
            }

            $this->addFlash('success', 'Tâche supprimée avec succès !');
            return $this->redirectToRoute('front_programme_show', ['id' => $programmeId]);
        }

        $this->addFlash('danger', 'Échec de la suppression.');
        return $this->redirectToRoute('front_programme_show', ['id' => $tache->getProgramme()->getId()]);
    }

    /**
     * Copie temporaire de updateProgrammeStats (à garder jusqu'à ce que tu crées un service partagé)
     */
   private function updateProgrammeStats(Programme $programme, EntityManagerInterface $entityManager): void
{
    $taches = $programme->getTache()->toArray();
    usort($taches, fn($a, $b) => $a->getOrdre() <=> $b->getOrdre());
    $total = count($taches);

    if ($total === 0) {
        $programme->setScorePourcentage(0);
        $programme->setMeilleureMedaille(null);
        if ($programme->getObjectif()) {
            $programme->getObjectif()->setStatut(\App\Enum\Statutobj::Abandonner);
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

    // ✅ Génération message motivant avec Ollama
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

    // Sauvegarde message motivant
    $motivation = new \App\Entity\Motivation();
    $motivation->setMessagemotivant($messageMotivant);
    $motivation->setDategeneratiomm(new \DateTime());
    $motivation->setProgramme($programme);

    $entityManager->persist($motivation);
    $entityManager->flush();
}
}