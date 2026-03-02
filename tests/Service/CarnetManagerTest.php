<?php

namespace App\Tests\Service;

use App\Entity\Carnet;
use App\Service\CarnetManager;
use PHPUnit\Framework\TestCase;

class CarnetManagerTest extends TestCase
{
    public function testCarnetValide(): void
    {
        $carnet = new Carnet();
        $carnet->setTitre('Note de révision');
        $carnet->setDateCreation(new \DateTime('2026-03-01 10:00:00'));
        $carnet->setDateModification(new \DateTime('2026-03-01 12:00:00'));

        $manager = new CarnetManager();

        $this->assertTrue($manager->validate($carnet));
    }

    public function testCarnetSansTitre(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $carnet = new Carnet();
        $carnet->setTitre('   ');
        $carnet->setDateCreation(new \DateTime('2026-03-01 10:00:00'));
        $carnet->setDateModification(new \DateTime('2026-03-01 12:00:00'));

        $manager = new CarnetManager();
        $manager->validate($carnet);
    }

    public function testCarnetAvecDateModificationAvantDateCreation(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $carnet = new Carnet();
        $carnet->setTitre('Carnet Test');
        $carnet->setDateCreation(new \DateTime('2026-03-02 10:00:00'));
        $carnet->setDateModification(new \DateTime('2026-03-01 10:00:00'));

        $manager = new CarnetManager();
        $manager->validate($carnet);
    }
}
