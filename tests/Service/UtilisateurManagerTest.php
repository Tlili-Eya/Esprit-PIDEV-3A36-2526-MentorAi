<?php

namespace App\Tests\Service;

use App\Entity\Utilisateur;
use App\Service\UtilisateurManager;
use PHPUnit\Framework\TestCase;

class UtilisateurManagerTest extends TestCase
{
    // ✅ Test 1 : Utilisateur valide
    public function testUtilisateurValide(): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setNom('Ben Ali');
        $utilisateur->setPrenom('Ahmed');
        $utilisateur->setEmail('ahmed.benali@gmail.com');
        $utilisateur->setRole('mentor');

        $manager = new UtilisateurManager();
        $this->assertTrue($manager->validate($utilisateur));
    }

    // ❌ Test 2 : Nom manquant
    public function testUtilisateurSansNom(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $utilisateur = new Utilisateur();
        $utilisateur->setPrenom('Ahmed');
        $utilisateur->setEmail('ahmed@gmail.com');
        $utilisateur->setRole('mentor');

        $manager = new UtilisateurManager();
        $manager->validate($utilisateur);
    }

    // ❌ Test 3 : Prénom manquant
    public function testUtilisateurSansPrenom(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prénom est obligatoire');

        $utilisateur = new Utilisateur();
        $utilisateur->setNom('Ben Ali');
        $utilisateur->setEmail('ahmed@gmail.com');
        $utilisateur->setRole('apprenant');

        $manager = new UtilisateurManager();
        $manager->validate($utilisateur);
    }

    // ❌ Test 4 : Email invalide
    public function testUtilisateurEmailInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Email invalide');

        $utilisateur = new Utilisateur();
        $utilisateur->setNom('Ben Ali');
        $utilisateur->setPrenom('Ahmed');
        $utilisateur->setEmail('email_invalide');
        $utilisateur->setRole('mentor');

        $manager = new UtilisateurManager();
        $manager->validate($utilisateur);
    }

    // ❌ Test 5 : Rôle invalide
    public function testUtilisateurRoleInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Rôle invalide');

        $utilisateur = new Utilisateur();
        $utilisateur->setNom('Ben Ali');
        $utilisateur->setPrenom('Ahmed');
        $utilisateur->setEmail('ahmed@gmail.com');
        $utilisateur->setRole('inconnu');

        $manager = new UtilisateurManager();
        $manager->validate($utilisateur);
    }
}