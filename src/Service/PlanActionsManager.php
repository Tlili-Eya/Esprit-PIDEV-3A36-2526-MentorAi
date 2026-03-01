<?php

namespace App\Service;

use App\Entity\PlanActions;

class PlanActionsManager
{
    /**
     * Valide un Plan d'action selon les règles métier.
     * 
     * @param PlanActions $plan
     * @return bool
     * @throws \InvalidArgumentException
     */
    public function validate(PlanActions $plan): bool
    {
        // Règle 1 : La décision (titre) est obligatoire et >= 5 caractères
        if (empty($plan->getDecision())) {
            throw new \InvalidArgumentException('La décision est obligatoire');
        }
        if (strlen($plan->getDecision()) < 5) {
            throw new \InvalidArgumentException('La décision doit contenir au moins 5 caractères');
        }

        // Règle 2 : La description est obligatoire et >= 10 caractères
        if (empty($plan->getDescription())) {
            throw new \InvalidArgumentException('La description est obligatoire');
        }
        if (strlen($plan->getDescription()) < 10) {
            throw new \InvalidArgumentException('La description doit contenir au moins 10 caractères');
        }

        // Règle 3 : Le statut est obligatoire
        if ($plan->getStatut() === null) {
            throw new \InvalidArgumentException('Le statut est obligatoire');
        }

        return true;
    }
}
