<?php

namespace App\Tests\Service;

use App\Entity\PlanActions;
use App\Service\PlanActionsManager;
use PHPUnit\Framework\TestCase;

class PlanActionsManagerTest extends TestCase
{
    public function testValidPlan()
    {
        $plan = new PlanActions();
        $plan->setDecision('Réunion de suivi');
        $plan->setDescription('Description détaillée du plan d\'action pour le mentorat.');

        $manager = new PlanActionsManager();

        $this->assertTrue($manager->validate($plan));
    }

    public function testPlanWithoutDecision()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La décision est obligatoire et doit contenir au moins 5 caractères');

        $plan = new PlanActions();
        $plan->setDecision('A'); // Trop court
        $plan->setDescription('Description valide de plus de 10 caractères.');

        $manager = new PlanActionsManager();
        $manager->validate($plan);
    }

    public function testPlanWithShortDescription()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description est obligatoire et doit contenir au moins 10 caractères');

        $plan = new PlanActions();
        $plan->setDecision('Décision valide');
        $plan->setDescription('Court'); // Trop court

        $manager = new PlanActionsManager();
        $manager->validate($plan);
    }
}
