<?php

namespace App\Service;

use App\Entity\Parcours;

class ParcoursManager
{
    public function validate(Parcours $parcours): bool
    {
        if ($this->isBlank($parcours->getTypeParcours())) {
            throw new \InvalidArgumentException('Le type de parcours est obligatoire');
        }

        if ($this->isBlank($parcours->getTitre())) {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }

        if (null !== $parcours->getTitre() && mb_strlen(trim($parcours->getTitre())) < 3) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 3 caractères');
        }

        if ($this->isBlank($parcours->getDescription())) {
            throw new \InvalidArgumentException('La description est obligatoire');
        }

        if (null === $parcours->getDateDebut()) {
            throw new \InvalidArgumentException('La date de début est obligatoire');
        }

        if (null !== $parcours->getDateFin() && $parcours->getDateFin() < $parcours->getDateDebut()) {
            throw new \InvalidArgumentException('La date de fin ne peut pas être antérieure à la date de début');
        }

        return true;
    }

    private function isBlank(?string $value): bool
    {
        return null === $value || '' === trim($value);
    }
}
