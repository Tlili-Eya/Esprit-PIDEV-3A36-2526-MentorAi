<?php

namespace App\Service;

use App\Entity\Objectif;
use App\Entity\Programme;
use App\Enum\Statutobj;
use Doctrine\ORM\EntityManagerInterface;

class ObjectifStatusService 
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Met à jour automatiquement le statut de l'objectif en fonction du score du programme
     */
    public function updateStatusFromProgrammeScore(Objectif $objectif): void
    {
        $programme = $objectif->getProgramme();
        if (!$programme) {
            return;
        }

       $score = $programme->getScorePourcentage();

        $newStatus = match (true) {
            $score === 0    => Statutobj::Abandonner,
            $score === 100  => Statutobj::Atteint,
            default         => Statutobj::EnCours,
        };

        // Mise à jour uniquement si nécessaire (évite flush inutile)
        if ($objectif->getStatut() !== $newStatus) {
            $objectif->setStatut($newStatus);
            $this->entityManager->flush();
        }
    }
}