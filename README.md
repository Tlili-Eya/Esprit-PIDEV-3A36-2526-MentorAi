# MentorAI 🚀 - Se former autrement (Partie Web)

MentorAI est une plateforme innovante conçue pour accompagner les étudiants de l'**ESPRIT** (Tunisie) dans leur réussite académique. Ce dépôt contient la partie **Web** du projet développée avec **Symfony**.

---

## 📖 Sommaire
1. [Introduction](#introduction)
2. [Fonctionnalités Principales](#fonctionnalités-principales)
3. [Architecture du Projet (MVC)](#architecture-du-projet-mvc)
4. [Modules du Projet](#modules-du-projet)
5. [Technologies Utilisées](#technologies-utilisées)
6. [Installation et Utilisation](#installation-et-utilisation)
7. [L'Équipe](#léquipe)

---

## 🌟 Introduction
Dans le milieu exigeant des formations d'ingénierie, les étudiants font face à une pression académique élevée et une surcharge cognitive. MentorAI propose un suivi intelligent et individualisé pour chaque étudiant, tout en offrant des outils décisionnels puissants pour les enseignants et l'administration.

---

## 🛠 Fonctionnalités Principales
- **Apprentissage personnalisé** : Adaptation du contenu selon le profil psychologique et le rythme de l'étudiant.
- **Assistance intelligente** : Chatbot IA intégré et analyse de performance en temps réel.
- **Organisation & Productivité** : Suivi des objectifs (Goal Tracker).
- **Contrôle Gestuel** : Utilisation de la caméra pour interagir avec des jeux pédagogiques (Hand Tracking).

---

## 🏗 Architecture du Projet (MVC)
La partie Web suit l'architecture standard de **Symfony** :

### 🔹 Modèle (Model)
- **Entités** : Situées dans `src/Entity`. Définit la structure de la base de données via Doctrine ORM.
- **Repositories** : Situés dans `src/Repository`. Gère les requêtes vers la base de données.

### 🔹 Vue (View)
- **Twig** : Templates situés dans `templates/`. Gère le rendu HTML/CSS dynamique.
- **Assets** : Fichiers CSS, JS et images situés dans `public/`.

### 🔹 Contrôleur (Controller)
- **Contrôleurs Symfony** : Situés dans `src/Controller`. Reçoit les requêtes HTTP et retourne les vues.

---

## 📦 Modules du Projet

### 01. Gestion des Utilisateurs
- Authentification sécurisée et gestion des profils.
- Attribution des rôles (Étudiant, Enseignant, Administrateur).

### 02. Dashboard Enseignants & Admin
- Analyse comportementale et académique.
- Évaluation de l'état global (performance, risque, engagement).
- Recommandations stratégiques basées sur l'IA.

### 03. Goal Tracker & Productivité
- Définition d'objectifs et suivi de progression via des tâches.
- Gamification : Attribution de scores et de médailles.

### 04. Portfolio & Orientation
- Accompagnement dans la construction du parcours professionnel.
- Gestion des projets et des compétences acquises.

### 05. Psychologie & Révision IA
- Personnalisation selon le style d'apprentissage et l'humeur.
- Résumés de cours automatiques et révisions adaptées.

### 06. Feedback & Amélioration
- Système de retour utilisateur pour améliorer continuellement l'agent intelligent.

---

## 💻 Technologies Utilisées
- **Framework** : Symfony 6.x / 7.x
- **Moteur de Template** : Twig
- **Base de Données** : MySQL / MariaDB
- **IA & Vision** :
  - **MediaPipe** (Hand Tracking via JS)
  - **Groq API** (Llama 3 pour le Chatbot)
  - **Confetti.js** (Gamification)

---

## 🚀 Installation et Utilisation

### Prérequis
- PHP 8.1+
- Composer
- Serveur MySQL

### Lancement
1. Clonez le dépôt.
2. Installez les dépendances :
   ```bash
   composer install
   ```
3. Configurez votre fichier `.env` (DATABASE_URL).
4. Créez la base de données et jouez les migrations :
   ```bash
   php bin/console doctrine:database:create
   php bin/console doctrine:migrations:migrate
   ```
5. Lancez le serveur local :
   ```bash
   php -S localhost:8000 -t public
   ```

---

## 👥 L'Équipe
Ce projet a été réalisé par :
- **Hajer Hmaied**
- **Mariem Amdouni**
- **Imen Azouzi**
- **Arlsen Amira**
- **Eya Tlili**
- **Amal Mokdad**

---
© 2026 MentorAI - Se former autrement.
