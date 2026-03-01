<?php

namespace App\Service;

use App\Entity\PlanActions;

class PlanActionsManager
{
    public function validate(PlanActions $plan): bool
    {
        if (empty($plan->getDecision()) || strlen($plan->getDecision()) < 5) {
            throw new \InvalidArgumentException('La décision est obligatoire et doit contenir au moins 5 caractères');
        }

        if (empty($plan->getDescription()) || strlen($plan->getDescription()) < 10) {
            throw new \InvalidArgumentException('La description est obligatoire et doit contenir au moins 10 caractères');
        }

        return true;
    }
}
