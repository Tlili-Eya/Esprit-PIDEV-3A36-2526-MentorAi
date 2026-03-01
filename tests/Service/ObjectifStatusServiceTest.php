<?php

namespace App\Tests\Service;

use App\Entity\Objectif;
use App\Entity\Programme;
use App\Enum\Statutobj;
use App\Service\ObjectifStatusService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;


class ObjectifStatusServiceTest extends TestCase
{
    private ObjectifStatusService $service;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->service = new ObjectifStatusService($this->entityManager);
    }

    // Test 1 : score = 0 → statut doit être Abandonner
    public function testScoreZeroSetStatutAbandonner(): void
    {
        $programme = new Programme();
        $programme->setScorePourcentage(0);

        $objectif = new Objectif();
        $objectif->setProgramme($programme);
        $objectif->setStatut(Statutobj::EnCours);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->updateStatusFromProgrammeScore($objectif);

        $this->assertSame(Statutobj::Abandonner, $objectif->getStatut());
    }

    // Test 2 : score = 100 → statut doit être Atteint
    public function testScoreCentSetStatutAtteint(): void
    {
        $programme = new Programme();
        $programme->setScorePourcentage(100);

        $objectif = new Objectif();
        $objectif->setProgramme($programme);
        $objectif->setStatut(Statutobj::EnCours);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->updateStatusFromProgrammeScore($objectif);

        $this->assertSame(Statutobj::Atteint, $objectif->getStatut());
    }

    // Test 3 : score entre 1 et 99 → statut doit être EnCours
    public function testScoreIntermediaireSetStatutEnCours(): void
    {
        $programme = new Programme();
        $programme->setScorePourcentage(50);

        $objectif = new Objectif();
        $objectif->setProgramme($programme);
        $objectif->setStatut(Statutobj::Abandonner);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->updateStatusFromProgrammeScore($objectif);

        $this->assertSame(Statutobj::EnCours, $objectif->getStatut());
    }

    // Test 4 : statut déjà correct → flush ne doit PAS être appelé
    public function testFlushNotCalledIfStatutUnchanged(): void
    {
        $programme = new Programme();
        $programme->setScorePourcentage(100);

        $objectif = new Objectif();
        $objectif->setProgramme($programme);
        $objectif->setStatut(Statutobj::Atteint); // déjà le bon statut

        $this->entityManager->expects($this->never())->method('flush');

        $this->service->updateStatusFromProgrammeScore($objectif);

        $this->assertSame(Statutobj::Atteint, $objectif->getStatut());
    }

    // Test 5 : pas de programme → rien ne se passe
    public function testSansProgrammeNeFaitRien(): void
    {
        $objectif = new Objectif();
        $objectif->setStatut(Statutobj::EnCours);

        $this->entityManager->expects($this->never())->method('flush');

        $this->service->updateStatusFromProgrammeScore($objectif);

        $this->assertSame(Statutobj::EnCours, $objectif->getStatut());
    }
    // Test 6 : score = 1 (limite basse) → statut doit être EnCours
    public function testScoreUnSetStatutEnCours(): void
    {
        $programme = new Programme();
        $programme->setScorePourcentage(1);

        $objectif = new Objectif();
        $objectif->setProgramme($programme);
        $objectif->setStatut(Statutobj::Abandonner);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->updateStatusFromProgrammeScore($objectif);

        $this->assertSame(Statutobj::EnCours, $objectif->getStatut());
    }
    
}