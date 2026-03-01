<?php

namespace App\Service;

use App\Entity\Utilisateur;

class UtilisateurManager
{
    public function validate(Utilisateur $utilisateur): bool
    {
        // Règle 1 : Le nom est obligatoire
        if (empty($utilisateur->getNom())) {
            throw new \InvalidArgumentException('Le nom est obligatoire');
        }

        // Règle 2 : Le prénom est obligatoire
        if (empty($utilisateur->getPrenom())) {
            throw new \InvalidArgumentException('Le prénom est obligatoire');
        }

        // Règle 3 : L'email doit être valide
        if (!filter_var($utilisateur->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Email invalide');
        }

        // Règle 4 : Le rôle doit être autorisé
        $rolesAutorises = ['mentor', 'apprenant', 'admin'];
        if (!in_array($utilisateur->getRole(), $rolesAutorises)) {
            throw new \InvalidArgumentException('Rôle invalide');
        }

        return true;
    }
}