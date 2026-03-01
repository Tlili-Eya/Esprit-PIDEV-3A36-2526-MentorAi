<?php

namespace App\Tests\Service;

use App\Entity\PlanActions;
use App\Service\PlanActionsManager;
use App\Enum\Statut;
use PHPUnit\Framework\TestCase;

class PlanActionsManagerTest extends TestCase
{
    private PlanActionsManager $manager;

    protected function setUp(): void
    {
        $this->manager = new PlanActionsManager();
    }

    public function testValidPlan()
    {
        $plan = new PlanActions();
        $plan->setDecision('Plan de révision Symfony');
        $plan->setDescription('Ce plan détaille les étapes pour apprendre les tests unitaires.');
        $plan->setStatut(Statut::EnCours);

        $this->assertTrue($this->manager->validate($plan));
    }

    public function testPlanWithoutDecision()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La décision est obligatoire');

        $plan = new PlanActions();
        $plan->setDescription('Description valide de plus de 10 caractères');
        $plan->setStatut(Statut::EnCours);

        $this->manager->validate($plan);
    }

    public function testPlanWithShortDecision()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La décision doit contenir au moins 5 caractères');

        $plan = new PlanActions();
        $plan->setDecision('Plan'); // 4 caractères
        $plan->setDescription('Description valide de plus de 10 caractères');
        $plan->setStatut(Statut::EnCours);

        $this->manager->validate($plan);
    }

    public function testPlanWithShortDescription()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La description doit contenir au moins 10 caractères');

        $plan = new PlanActions();
        $plan->setDecision('Plan valide');
        $plan->setDescription('Court'); // 5 caractères
        $plan->setStatut(Statut::EnCours);

        $this->manager->validate($plan);
    }

    public function testPlanWithoutStatut()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le statut est obligatoire');

        $plan = new PlanActions();
        $plan->setDecision('Plan valide');
        $plan->setDescription('Description valide de plus de 10 caractères');
        // Statut non défini

        $this->manager->validate($plan);
    }
}
