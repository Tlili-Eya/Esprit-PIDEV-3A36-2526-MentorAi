<?php

namespace App\Tests\Service;

use App\Entity\Parcours;
use App\Service\ParcoursManager;
use PHPUnit\Framework\TestCase;

class ParcoursManagerTest extends TestCase
{
    public function testParcoursValide(): void
    {
        $parcours = new Parcours();
        $parcours->setTypeParcours('Académique');
        $parcours->setTitre('Master IA');
        $parcours->setDescription('Parcours de spécialisation en IA.');
        $parcours->setDateDebut(new \DateTime('2024-09-01'));
        $parcours->setDateFin(new \DateTime('2026-06-30'));

        $manager = new ParcoursManager();

        $this->assertTrue($manager->validate($parcours));
    }

    public function testParcoursSansType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $parcours = new Parcours();
        $parcours->setTitre('Master Data');
        $parcours->setDescription('Description valide');
        $parcours->setDateDebut(new \DateTime('2024-09-01'));

        $manager = new ParcoursManager();
        $manager->validate($parcours);
    }

    public function testParcoursAvecDateFinAvantDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $parcours = new Parcours();
        $parcours->setTypeParcours('Professionnel');
        $parcours->setTitre('Parcours Pro');
        $parcours->setDescription('Description valide');
        $parcours->setDateDebut(new \DateTime('2026-09-01'));
        $parcours->setDateFin(new \DateTime('2026-01-01'));

        $manager = new ParcoursManager();
        $manager->validate($parcours);
    }
}
