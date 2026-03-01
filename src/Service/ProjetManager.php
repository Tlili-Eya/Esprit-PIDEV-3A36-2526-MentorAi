<?php

namespace App\Service;

use App\Entity\Projet;

class ProjetManager
{
    public function validate(Projet $projet): bool
    {
        if ($this->isBlank($projet->getTitre())) {
            throw new \InvalidArgumentException('Le titre du projet est obligatoire');
        }

        if ($this->isBlank($projet->getType())) {
            throw new \InvalidArgumentException('Le type du projet est obligatoire');
        }

        if ($this->isBlank($projet->getTechnologies())) {
            throw new \InvalidArgumentException('Les technologies sont obligatoires');
        }

        if (null === $projet->getDateDebut()) {
            throw new \InvalidArgumentException('La date de début est obligatoire');
        }

        if (null !== $projet->getDateFin() && $projet->getDateFin() < $projet->getDateDebut()) {
            throw new \InvalidArgumentException('La date de fin ne peut pas être antérieure à la date de début');
        }

        return true;
    }

    private function isBlank(?string $value): bool
    {
        return null === $value || '' === trim($value);
    }
}
