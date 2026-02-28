#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Analyseur de risque utilisateur — Système expert à règles pondérées
Exécuté par Symfony via symfony/process (AUCUN appel API externe)

Usage : python analyser_utilisateur.py '{"id":1,"nom":"Dupont",...}'
Sortie : texte français du verdict sur stdout
Erreur : "ERREUR: ..." sur stdout + exit(1)
"""

import sys
import io
import json
import re
from datetime import datetime, date

# Force l'encodage UTF-8 sur Windows (évite les erreurs charmap)
if hasattr(sys.stdout, 'buffer'):
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')
if hasattr(sys.stderr, 'buffer'):
    sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8', errors='replace')


# ===========================================================================
# CONFIGURATION — LISTES ET SEUILS
# ===========================================================================

# Domaines d'e-mails jetables (temporaires)
DOMAINES_JETABLES = {
    "mailinator.com", "guerrillamail.com", "yopmail.fr", "yopmail.com",
    "10minutemail.com", "10minutemail.net", "tempmail.com", "throwaway.email",
    "sharklasers.com", "guerrillamailblock.com", "grr.la", "guerrillamail.info",
    "spam4.me", "trashmail.com", "trashmail.me", "dispostable.com",
    "mailnull.com", "spamgourmet.com", "maildrop.cc", "getairmail.com",
    "fakeinbox.com", "mailexpire.com", "spambox.us", "mytrashmail.com",
    "discard.email", "spamfree24.org", "jetable.fr.nf", "nospam.ze.tc",
    "trashmail.at", "trashmail.io", "filzmail.com", "tempr.email",
    "discard.email", "temp-mail.org", "temp-mail.ru", "fakemailgenerator.com",
}

# Noms/prénoms génériques suspects
NOMS_GENERIQUES = {
    "admin", "administrateur", "administrator", "test", "user", "utilisateur",
    "demo", "exemple", "example", "guest", "invité", "invite", "anonymous",
    "anon", "superuser", "root", "system", "default", "toto", "titi", "tata",
    "aaa", "bbb", "xxx", "null", "none", "unknown", "inconnu",
}

# Plages IP privées / locales suspectes
IP_PREFIXES_LOCAUX = (
    "192.168.", "10.", "172.16.", "172.17.", "172.18.", "172.19.",
    "172.20.", "172.21.", "172.22.", "172.23.", "172.24.", "172.25.",
    "172.26.", "172.27.", "172.28.", "172.29.", "172.30.", "172.31.",
    "127.", "0.0.0.0", "::1", "localhost",
)

# Année minimale raisonnable pour une inscription
ANNEE_MIN_INSCRIPTION = 2020

# Score de départ
SCORE_BASE = 100


# ===========================================================================
# FONCTIONS UTILITAIRES
# ===========================================================================

def safe_get(data: dict, key: str, default=None):
    """Récupère une valeur du dictionnaire sans planter si la clé est absente."""
    return data.get(key, default)


def extraire_domaine(email: str) -> str:
    """Extrait le domaine d'une adresse e-mail."""
    if not email or "@" not in email:
        return ""
    return email.split("@")[-1].strip().lower()


def est_domaine_jetable(email: str) -> bool:
    """Vérifie si l'e-mail appartient à un domaine jetable connu."""
    return extraire_domaine(email) in DOMAINES_JETABLES


def est_ip_locale(ip: str) -> bool:
    """Vérifie si l'IP d'inscription est une IP de réseau local."""
    if not ip:
        return False
    ip = ip.strip().lower()
    return any(ip.startswith(prefix) for prefix in IP_PREFIXES_LOCAUX)


def est_nom_generique(nom: str, prenom: str) -> bool:
    """Vérifie si le nom ou le prénom est un identifiant générique."""
    nom_norm    = (nom    or "").strip().lower()
    prenom_norm = (prenom or "").strip().lower()
    return nom_norm in NOMS_GENERIQUES or prenom_norm in NOMS_GENERIQUES


def email_contient_identite(email: str, nom: str, prenom: str) -> bool:
    """
    Vérifie si l'e-mail contient une partie du nom ou du prénom.
    Heuristique simple : si les 3+ premières lettres du nom/prénom
    apparaissent dans la partie locale de l'e-mail.
    """
    if not email or "@" not in email:
        return False
    partie_locale = email.split("@")[0].lower()
    nom_court    = (nom    or "")[:3].lower()
    prenom_court = (prenom or "")[:3].lower()
    if len(nom_court) >= 3 and nom_court in partie_locale:
        return True
    if len(prenom_court) >= 3 and prenom_court in partie_locale:
        return True
    return False


def parser_date(date_str) -> date | None:
    """
    Tente de parser une date depuis différents formats.
    Retourne None si la date est invalide ou absente.
    """
    if not date_str or str(date_str).strip() in ("", "null", "None", "jamais"):
        return None
    formats = ["%Y-%m-%d", "%d/%m/%Y", "%d-%m-%Y", "%Y/%m/%d"]
    for fmt in formats:
        try:
            return datetime.strptime(str(date_str).strip()[:10], fmt).date()
        except ValueError:
            continue
    return None


def date_inscription_valide(date_obj: date | None) -> bool:
    """Une date est valide si elle est entre 2020 et aujourd'hui."""
    if date_obj is None:
        return False
    aujourd_hui = date.today()
    return ANNEE_MIN_INSCRIPTION <= date_obj.year <= aujourd_hui.year and date_obj <= aujourd_hui


def inscription_trop_recente(date_obj: date | None, jours: int = 1) -> bool:
    """Vérifie si l'inscription a eu lieu dans les dernières N heures/jours."""
    if date_obj is None:
        return False
    delta = (date.today() - date_obj).days
    return delta <= jours


# ===========================================================================
# MOTEUR D'ANALYSE — RÈGLES PONDÉRÉES
# ===========================================================================

def analyser(data: dict) -> dict:
    """
    Applique un ensemble de règles pondérées sur le profil utilisateur.

    Retourne un dictionnaire avec :
    - score       : entier 0–100
    - verdict     : "FIABLE" | "NEUTRE" | "SUSPECT"
    - observations: liste de phrases décrivant les anomalies détectées
    - points_bonus: liste de points positifs
    """
    score        = SCORE_BASE
    observations = []   # anomalies détectées (pénalités)
    points_bonus = []   # éléments rassurants (bonus)

    # --- Extraction des données ------------------------------------------------
    id_user         = safe_get(data, "id", "?")
    nom             = safe_get(data, "nom", "")          or ""
    prenom          = safe_get(data, "prenom", "")       or ""
    email           = safe_get(data, "email", "")        or ""
    role            = safe_get(data, "role", "")         or ""
    date_insc_str   = safe_get(data, "date_inscription", None)
    derniere_co     = safe_get(data, "derniere_connexion", "jamais") or "jamais"
    tentatives      = int(safe_get(data, "tentatives_echouees", 0) or 0)
    ip              = safe_get(data, "ip", "")           or ""
    trust_score     = float(safe_get(data, "trust_score", 50) or 50)
    risk_level      = str(safe_get(data, "risk_level", "MEDIUM") or "MEDIUM").upper()
    doublon_ip      = bool(safe_get(data, "doublon_ip", False))
    a_photo         = bool(safe_get(data, "a_photo", False))

    date_insc = parser_date(date_insc_str)

    # ===========================================================
    # RÈGLE 1 — Tentatives de connexion échouées
    # ===========================================================
    if tentatives == 0:
        points_bonus.append("Aucune tentative de connexion échouée enregistrée.")
    elif 1 <= tentatives <= 3:
        score -= 5
        observations.append(
            f"{tentatives} tentative(s) de connexion échouée(s) — risque modéré."
        )
    elif 4 <= tentatives <= 6:
        score -= 15
        observations.append(
            f"{tentatives} tentatives de connexion échouées — comportement suspect (possibles attaques brute-force)."
        )
    else:  # > 6
        score -= 30
        observations.append(
            f"⚠ {tentatives} tentatives de connexion échouées — risque élevé de compromission ou d'attaque brute-force."
        )

    # ===========================================================
    # RÈGLE 2 — E-mail jetable
    # ===========================================================
    if est_domaine_jetable(email):
        score -= 25
        domaine = extraire_domaine(email)
        observations.append(
            f"L'adresse e-mail utilise un domaine jetable connu ({domaine}), "
            "souvent associé à des comptes temporaires ou frauduleux."
        )
    else:
        points_bonus.append("Le domaine de l'e-mail n'est pas dans la liste noire des domaines jetables.")

    # ===========================================================
    # RÈGLE 3 — Doublon IP
    # ===========================================================
    if doublon_ip:
        score -= 25
        observations.append(
            "Plusieurs comptes partagent la même adresse IP d'inscription — "
            "potentielle création multiple de comptes depuis un même poste."
        )
    else:
        points_bonus.append("Aucun doublon d'adresse IP détecté.")

    # ===========================================================
    # RÈGLE 4 — Nom/prénom générique
    # ===========================================================
    if est_nom_generique(nom, prenom):
        score -= 20
        observations.append(
            f"Le nom « {prenom} {nom} » est un identifiant générique "
            "(admin, test, user…) courant dans les comptes automatiques ou de test."
        )

    # ===========================================================
    # RÈGLE 5 — IP locale / réseau interne
    # ===========================================================
    if est_ip_locale(ip):
        score -= 8
        observations.append(
            f"L'IP d'inscription ({ip}) appartient à un réseau local ou privé — "
            "l'accès semble provenir d'un réseau interne non exposé à Internet."
        )
    elif ip and ip not in ("", "null", "None"):
        points_bonus.append(f"IP d'inscription ({ip}) apparemment publique.")

    # ===========================================================
    # RÈGLE 6 — Jamais connecté
    # ===========================================================
    jamais_connecte = str(derniere_co).strip().lower() in ("jamais", "never", "null", "none", "")
    if jamais_connecte:
        score -= 10
        observations.append(
            "Le compte n'a jamais été utilisé depuis sa création — "
            "peut indiquer un compte fantôme ou abandonné."
        )
    else:
        points_bonus.append(f"Dernière connexion enregistrée : {derniere_co}.")

    # ===========================================================
    # RÈGLE 7 — Date d'inscription
    # ===========================================================
    if date_insc is None:
        score -= 15
        observations.append(
            "La date d'inscription est absente ou dans un format invalide — "
            "données corrompues ou manipulation possible."
        )
    elif not date_inscription_valide(date_insc):
        score -= 15
        observations.append(
            f"La date d'inscription ({date_insc}) est en dehors de la plage attendue "
            f"(entre {ANNEE_MIN_INSCRIPTION} et aujourd'hui)."
        )
    else:
        points_bonus.append(f"Date d'inscription cohérente ({date_insc}).")
        # Sous-règle : inscription très récente (< 24h) + jamais connecté
        if inscription_trop_recente(date_insc, jours=1) and jamais_connecte:
            score -= 8
            observations.append(
                "Compte créé très récemment (moins de 24h) et déjà sans connexion — "
                "profil non activé ou bot d'inscription."
            )

    # ===========================================================
    # RÈGLE 8 — Photo de profil absente
    # ===========================================================
    if not a_photo:
        score -= 5
        observations.append("Aucune photo de profil renseignée.")
    else:
        points_bonus.append("Photo de profil présente.")

    # ===========================================================
    # RÈGLE 9 — E-mail sans correspondance avec le nom/prénom
    # ===========================================================
    if not email_contient_identite(email, nom, prenom):
        score -= 5
        observations.append(
            "L'adresse e-mail ne contient pas le nom ou le prénom de l'utilisateur — "
            "e-mail possiblement partagé ou générique."
        )
    else:
        points_bonus.append("L'e-mail semble correspondre à l'identité déclarée.")

    # ===========================================================
    # RÈGLE 10 — Trust score interne incohérent
    # ===========================================================
    if trust_score < 20:
        score -= 20
        observations.append(
            f"Le Trust Score interne est très faible ({trust_score:.0f}/100) — "
            "le système de règles internes considère ce profil comme très risqué."
        )
    elif trust_score < 40:
        score -= 10
        observations.append(
            f"Le Trust Score interne est faible ({trust_score:.0f}/100)."
        )
    elif trust_score >= 80:
        score += 5
        points_bonus.append(f"Bon Trust Score interne ({trust_score:.0f}/100).")

    # ===========================================================
    # RÈGLE 11 — Niveau de risque déjà évalué HIGH
    # ===========================================================
    if risk_level == "HIGH":
        score -= 10
        observations.append(
            "Le niveau de risque interne (Risk Level) est déjà classifié « Élevé »."
        )
    elif risk_level == "LOW":
        score += 5
        points_bonus.append("Le niveau de risque interne est « Faible ».")

    # ===========================================================
    # RÈGLE 12 — Rôle administrateur avec profil générique
    # ===========================================================
    role_lower = role.strip().lower()
    est_admin  = role_lower in ("administrateur", "admin", "administrator", "adminm")
    if est_admin and (est_nom_generique(nom, prenom) or est_domaine_jetable(email)):
        score -= 15
        observations.append(
            "Compte avec rôle administrateur et profil générique ou e-mail jetable — "
            "combinaison très risquée nécessitant une vérification manuelle."
        )

    # ===========================================================
    # RÈGLE 13 — Combinaison critique (doublon + jamais connecté + attempts)
    # ===========================================================
    if doublon_ip and jamais_connecte and tentatives > 3:
        score -= 10
        observations.append(
            "Combinaison critique détectée : doublon IP + compte jamais utilisé + "
            "tentatives de connexion élevées — profil hautement suspect."
        )

    # ===========================================================
    # LIMITES : Score entre 0 et 100
    # ===========================================================
    score = max(0, min(100, score))

    # ===========================================================
    # CLASSIFICATION FINALE
    # ===========================================================
    if score >= 65:
        verdict = "FIABLE"
    elif score >= 35:
        verdict = "NEUTRE"
    else:
        verdict = "SUSPECT"

    return {
        "score":        score,
        "verdict":      verdict,
        "observations": observations,
        "points_bonus": points_bonus,
    }


# ===========================================================================
# GÉNÉRATION DU TEXTE DE SORTIE
# ===========================================================================

def generer_verdict_texte(data: dict, resultat: dict) -> str:
    """
    Génère un texte de verdict détaillé en français, structuré et lisible
    par un administrateur humain.
    """
    nom    = safe_get(data, "nom",    "") or ""
    prenom = safe_get(data, "prenom", "") or ""
    score  = resultat["score"]
    verdict = resultat["verdict"]
    obs     = resultat["observations"]
    bonus   = resultat["points_bonus"]

    # En-tête
    lignes = [
        f"Analyse du profil de {prenom} {nom} — Score de confiance : {score}/100.",
        "",
    ]

    # Points positifs (si présents)
    if bonus:
        lignes.append("✅ Points positifs :")
        for b in bonus[:3]:  # max 3 points bonus pour la lisibilité
            lignes.append(f"  • {b}")
        lignes.append("")

    # Anomalies détectées
    if obs:
        lignes.append("⚠ Anomalies détectées :")
        for o in obs:
            lignes.append(f"  • {o}")
        lignes.append("")
    else:
        lignes.append("Aucune anomalie significative détectée sur ce profil.")
        lignes.append("")

    # Verdict final
    emoji_verdict = {"FIABLE": "✅", "NEUTRE": "⚠", "SUSPECT": "🚨"}
    lignes.append(f"VERDICT: {verdict} {emoji_verdict.get(verdict, '')}")

    return "\n".join(lignes).strip()


# ===========================================================================
# POINT D'ENTRÉE PRINCIPAL
# ===========================================================================

def main():
    # ── Vérification de l'argument ───────────────────────────────────────────
    if len(sys.argv) < 2:
        print("ERREUR: Aucun argument JSON fourni. Usage : python analyser_utilisateur.py '{\"id\":1,...}'")
        sys.exit(1)

    json_brut = sys.argv[1]

    # ── Parsing JSON ─────────────────────────────────────────────────────────
    try:
        data = json.loads(json_brut)
    except json.JSONDecodeError as e:
        print(f"ERREUR: JSON invalide — {e}")
        sys.exit(1)

    # ── Analyse ──────────────────────────────────────────────────────────────
    try:
        resultat = analyser(data)
        texte    = generer_verdict_texte(data, resultat)
        print(texte)
    except Exception as e:
        print(f"ERREUR: Échec de l'analyse — {e}")
        sys.exit(1)


if __name__ == "__main__":
    main()
