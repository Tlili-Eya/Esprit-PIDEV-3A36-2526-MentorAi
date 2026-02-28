# Analyseur de Risque Utilisateur — Python Local

## Description
Script Python d'analyse de risque utilisateur basé sur un système expert à règles pondérées.
Exécuté localement par Symfony via `symfony/process` — **aucun appel API externe**.

## Prérequis
- Python **3.10 ou supérieur**
- Aucune bibliothèque externe (stdlib uniquement)

## Installation

### Windows
```batch
# Vérifier la version Python
python --version

# Tester le script directement
cd c:\projetwebv1\mentor
python python_ia\analyser_utilisateur.py "{\"id\":1,\"nom\":\"Dupont\",\"prenom\":\"Jean\",\"email\":\"jean.dupont@gmail.com\",\"role\":\"etudiant\",\"date_inscription\":\"2024-09-01\",\"derniere_connexion\":\"2025-01-10\",\"tentatives_echouees\":0,\"ip\":\"85.32.45.12\",\"trust_score\":85,\"risk_level\":\"LOW\",\"doublon_ip\":false,\"a_photo\":true}"
```

### Linux / macOS
```bash
# Vérifier la version Python
python3 --version

# Tester le script directement
cd /var/www/mentor
python3 python_ia/analyser_utilisateur.py '{"id":1,"nom":"Dupont","prenom":"Jean","email":"jean.dupont@gmail.com","role":"etudiant","date_inscription":"2024-09-01","derniere_connexion":"2025-01-10","tentatives_echouees":0,"ip":"85.32.45.12","trust_score":85,"risk_level":"LOW","doublon_ip":false,"a_photo":true}'
```

## Configuration Symfony
Dans `.env`, vous pouvez forcer le chemin Python :
```env
PYTHON_PATH=python
# ou
PYTHON_PATH=C:\Python312\python.exe
# ou laisser vide pour la détection automatique
```

## Règles appliquées
| Règle | Pénalité |
|-------|----------|
| Tentatives échouées 1–3 | −5 pts |
| Tentatives échouées 4–6 | −15 pts |
| Tentatives échouées > 6 | −30 pts |
| E-mail domaine jetable | −25 pts |
| Doublon IP | −25 pts |
| Nom/prénom générique | −20 pts |
| IP locale / réseau interne | −8 pts |
| Jamais connecté | −10 pts |
| Date inscription invalide | −15 pts |
| Inscription < 24h + jamais connecté | −8 pts |
| Pas de photo | −5 pts |
| E-mail sans identité | −5 pts |
| Trust Score < 20 | −20 pts |
| Trust Score 20–39 | −10 pts |
| Risk Level HIGH | −10 pts |
| Rôle admin + profil générique | −15 pts |
| Combo (doublon + jamais + 3+ tentatives) | −10 pts |

## Classification finale
| Score | Verdict |
|-------|---------|
| 65 – 100 | ✅ FIABLE |
| 35 – 64 | ⚠ NEUTRE |
| 0 – 34 | 🚨 SUSPECT |
