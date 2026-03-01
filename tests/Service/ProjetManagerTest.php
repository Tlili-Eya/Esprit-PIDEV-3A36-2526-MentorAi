<?php

namespace App\Tests\Service;

use App\Entity\Projet;
use App\Service\ProjetManager;
use PHPUnit\Framework\TestCase;

class ProjetManagerTest extends TestCase
{
    public function testProjetValide(): void
    {
        $projet = new Projet();
        $projet->setTitre('Projet Mentor');
        $projet->setType('Web');
        $projet->setTechnologies('Symfony, MySQL');
        $projet->setDateDebut(new \DateTime('2026-03-01'));
        $projet->setDateFin(new \DateTime('2026-03-15'));

        $manager = new ProjetManager();

        $this->assertTrue($manager->validate($projet));
    }

    public function testProjetSansTitre(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $projet = new Projet();
        $projet->setType('Web');
        $projet->setTechnologies('Symfony');
        $projet->setDateDebut(new \DateTime('2026-03-01'));

        $manager = new ProjetManager();
        $manager->validate($projet);
    }

    public function testProjetAvecDateFinAvantDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $projet = new Projet();
        $projet->setTitre('Projet Test');
        $projet->setType('Mobile');
        $projet->setTechnologies('Flutter');
        $projet->setDateDebut(new \DateTime('2026-03-10'));
        $projet->setDateFin(new \DateTime('2026-03-01'));

        $manager = new ProjetManager();
        $manager->validate($projet);
    }
}
