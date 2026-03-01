# Guide rapide — Tests unitaires et analyse statique PHPStan (Symfony)

Ce document explique **comment lancer, comprendre et corriger** les tests unitaires et l’analyse statique PHPStan dans ce projet.

## 1) Objectif

- Vérifier la logique métier avec des **tests unitaires** (PHPUnit).
- Détecter des erreurs de typage/logique **sans exécuter l’application** (PHPStan).
- Travailler progressivement : ciblé (`Projet`/`Parcours`) puis global.

---

## 2) Prérequis

- Dépendances installées via Composer.
- PHP >= 8.1.
- Depuis la racine du projet (`mentor`).

Commandes de base :

```bash
composer install
vendor/bin/phpstan --version
php bin/phpunit --version
```

---

## 3) Configuration PHPStan utilisée

Fichier : [phpstan.neon](phpstan.neon)

Points importants :

- `level: 8` (niveau strict)
- `paths: - src`
- règles `ignoreErrors` ciblées pour quelques cas (ex. `id` Doctrine auto-généré)

---

## 4) Commandes atelier (copier-coller)

### A. Vérifier PHPStan

```bash
vendor/bin/phpstan --version
```

### B. Analyse globale

```bash
vendor/bin/phpstan analyse src --no-progress
```

### C. Analyse ciblée Projet/Parcours (recommandée pour votre travail)

```bash
vendor/bin/phpstan analyse src/Entity/Projet.php src/Entity/Parcours.php src/Controller/ProjetController.php src/Controller/ParcoursController.php src/Service/ProjetManager.php src/Service/ParcoursManager.php tests/Service/ProjetManagerTest.php tests/Service/ParcoursManagerTest.php --no-progress
```

### D. Tests unitaires ciblés Projet/Parcours

```bash
php bin/phpunit tests/Service/ProjetManagerTest.php tests/Service/ParcoursManagerTest.php
```

### E. Tous les tests

```bash
php bin/phpunit
```

---

## 5) Comment lire les erreurs PHPStan

Format typique :

- fichier + ligne
- message
- identifiant (ex. `argument.type`, `method.nonObject`, `binaryOp.invalid`)

Exemples fréquents :

1. `argument.type`
   - Un type envoyé à une méthode ne correspond pas à ce qu’elle attend.
   - Correction : caster, vérifier le type (`instanceof`), ou typer la variable avant usage.

2. `method.nonObject`
   - Appel de méthode possible sur valeur `null`.
   - Correction : ajouter un garde-fou (`if ($x === null) { ... }`) avant l’appel.

3. `binaryOp.invalid`
   - Concaténation/operation avec un type incertain (`array|bool|...|null`).
   - Correction : valider le type attendu (`is_string(...)`) avant concaténation.

4. `missingType.*`
   - Paramètres/retours/propriétés sans type explicite.
   - Correction : ajouter type hint PHP et/ou PHPDoc précis.

---

## 6) Stratégie de correction recommandée

1. Lancer analyse ciblée (fichiers en cours).
2. Corriger les erreurs bloquantes de type/null.
3. Relancer PHPStan ciblé.
4. Lancer PHPUnit ciblé.
5. Élargir au reste du projet.

Boucle standard :

```text
analyse -> corrige -> analyse -> teste
```

---

## 7) Ce qui a été validé pour Projet/Parcours

- Entités : `Projet` et `Parcours` prises en compte.
- Services métier : `ProjetManager` et `ParcoursManager`.
- Contrôleurs : `ProjetController` et `ParcoursController`.
- Tests unitaires ciblés : `ProjetManagerTest` et `ParcoursManagerTest`.
- Analyse PHPStan ciblée : OK (aucune erreur sur ce périmètre).

---

## 8) Bonnes pratiques à garder

- Toujours typer les paramètres et retours.
- Toujours vérifier les valeurs potentiellement `null` avant appel de méthode.
- Préférer une analyse ciblée pendant le dev, puis globale avant livraison.
- Garder les tests unitaires à jour après chaque modification métier.

---

## 9) Résolution rapide de problèmes

- Si `vendor/bin/phpstan` ne passe pas :
  - vérifier `composer install`
  - vérifier le fichier [phpstan.neon](phpstan.neon)

- Si un test unitaire échoue :
  - exécuter le test ciblé seul
  - relire la règle métier testée (exception attendue ou `assertTrue`)

---

## 10) Commandes minimales à retenir

```bash
vendor/bin/phpstan analyse src --no-progress
vendor/bin/phpstan analyse src/Controller/ProjetController.php src/Controller/ParcoursController.php src/Service/ProjetManager.php src/Service/ParcoursManager.php --no-progress
php bin/phpunit tests/Service/ProjetManagerTest.php tests/Service/ParcoursManagerTest.php
php bin/phpunit
```
