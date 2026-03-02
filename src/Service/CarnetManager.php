<?php

namespace App\Service;

use App\Entity\Carnet;

class CarnetManager
{
    public function validate(Carnet $carnet): bool
    {
        $titre = $carnet->getTitre();
        if ($titre === null || trim($titre) === '') {
            throw new \InvalidArgumentException('Le titre du carnet est obligatoire.');
        }

        $dateCreation = $carnet->getDateCreation();
        $dateModification = $carnet->getDateModification();

        if ($dateCreation !== null && $dateModification !== null && $dateModification < $dateCreation) {
            throw new \InvalidArgumentException('La date de modification ne peut pas être antérieure à la date de création.');
        }

        return true;
    }
}
