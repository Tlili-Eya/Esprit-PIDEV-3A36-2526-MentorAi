<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Service d'analyse de risque utilisateur via un script Python local.
 *
 * Aucun appel API externe — tout s'exécute en local via symfony/process.
 * Le script Python reçoit les données en JSON (argument CLI) et retourne
 * le verdict textuel sur stdout.
 */
final class PythonLocalAnalyzer
{
    /**
     * Chemin vers le script Python, relatif à la racine du projet.
     * Correspond à : <project_root>/python_ia/analyser_utilisateur.py
     */
    private const SCRIPT_RELATIF = 'python_ia/analyser_utilisateur.py';

    /** Timeout en secondes pour l'exécution du script Python. */
    private const TIMEOUT = 10;

    /**
     * @param string $projectDir  Injecté automatiquement via %kernel.project_dir%
     * @param string $pythonPath  Chemin Python depuis .env (peut être vide → détection auto)
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly string $pythonPath = '',
    ) {}

    /**
     * Analyse un profil utilisateur et retourne le verdict textuel.
     *
     * @throws \RuntimeException si Python n'est pas disponible, si le script
     *                           est introuvable, ou si l'exécution échoue.
     */
    public function analyze(Utilisateur $user): string
    {
        // ── 1. Vérification que le script Python existe ──────────────────────
        $scriptPath = $this->projectDir . DIRECTORY_SEPARATOR . self::SCRIPT_RELATIF;

        if (!file_exists($scriptPath)) {
            throw new \RuntimeException(
                sprintf(
                    "Script Python introuvable : %s\n" .
                    "Créez le fichier python_ia/analyser_utilisateur.py à la racine du projet.",
                    $scriptPath
                )
            );
        }

        // ── 2. Sérialisation des données utilisateur en JSON ─────────────────
        $donnees = $this->construirePayload($user);

        $jsonDonnees = json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($jsonDonnees === false) {
            throw new \RuntimeException(
                'Impossible de sérialiser les données utilisateur en JSON : ' . json_last_error_msg()
            );
        }

        // ── 3. Détection automatique de l'interpréteur Python ───────────────
        $python = $this->detecterPython();

        // ── 4. Construction et exécution du processus ────────────────────────
        /*
         * On passe le JSON en argument positionnel (sys.argv[1] côté Python).
         * On utilise un tableau pour éviter les injections shell (Process l'échappe).
         */
        $process = new Process(
            [$python, $scriptPath, $jsonDonnees],
            $this->projectDir,          // répertoire de travail = racine du projet
            ['PYTHONIOENCODING' => 'utf-8'],  // force UTF-8 sur Windows
            null,                       // stdin non utilisé
            self::TIMEOUT
        );

        $process->run();

        // ── 5. Vérification du résultat ──────────────────────────────────────
        $stdout = trim($process->getOutput());
        $stderr = trim($process->getErrorOutput());

        // Le script Python imprime "ERREUR: ..." sur stdout avant exit(1)
        if (!$process->isSuccessful() || str_starts_with($stdout, 'ERREUR:')) {
            $detail = $stdout ?: $stderr ?: 'Aucun message d\'erreur retourné.';
            throw new \RuntimeException(
                sprintf(
                    "Le script Python a échoué (code %d) :\n%s",
                    $process->getExitCode(),
                    $detail
                )
            );
        }

        if (empty($stdout)) {
            throw new \RuntimeException(
                'Le script Python n\'a retourné aucun verdict (stdout vide).'
            );
        }

        return $stdout;
    }

    /**
     * Construit le tableau de données à passer au script Python.
     * Toutes les valeurs sont normalisées pour éviter les erreurs côté Python.
     *
     * @return array<string, int|float|string|bool|null>
     */
    private function construirePayload(Utilisateur $user): array
    {
        return [
            'id'                  => $user->getId(),
            'nom'                 => $user->getNom()    ?? '',
            'prenom'              => $user->getPrenom() ?? '',
            'email'               => $user->getEmail()  ?? '',
            'role'                => $user->getRole()   ?? '',
            'date_inscription'    => $user->getDateInscription()
                                        ? $user->getDateInscription()->format('Y-m-d')
                                        : null,
            'derniere_connexion'  => $user->getLastLogin()
                                        ? $user->getLastLogin()->format('Y-m-d H:i:s')
                                        : 'jamais',
            'tentatives_echouees' => $user->getLoginAttempts(),
            'ip'                  => $user->getRegistrationIp() ?? '',
            'trust_score'         => $user->getTrustScore(),
            'risk_level'          => $user->getRiskLevel(),
            'doublon_ip'          => (bool) $user->isFlaggedDuplicate(),
            'a_photo'             => (bool) $user->getPdpUrl(),
        ];
    }

    /**
     * Détecte automatiquement l'interpréteur Python disponible sur le système.
     *
     * Ordre de priorité :
     *  1. Variable d'environnement PYTHON_PATH (si définie dans .env)
     *  2. `python3` (Linux / macOS)
     *  3. `python`  (Windows)
     *
     * @throws \RuntimeException si aucun interpréteur Python n'est trouvé.
     */
    private function detecterPython(): string
    {
        // Priorité 1 : chemin explicite depuis .env
        if (!empty($this->pythonPath)) {
            return $this->pythonPath;
        }

        // Priorité 2 : détection automatique selon l'OS
        $candidats = PHP_OS_FAMILY === 'Windows'
            ? ['python', 'python3', 'py']   // Windows : `python` est prioritaire
            : ['python3', 'python'];         // Linux/macOS : `python3` prioritaire

        foreach ($candidats as $candidat) {
            $test = new Process([$candidat, '--version']);
            $test->run();
            if ($test->isSuccessful()) {
                return $candidat;
            }
        }

        throw new \RuntimeException(
            "Python est introuvable sur ce système.\n" .
            "Installez Python 3.10+ et ajoutez-le au PATH, ou définissez PYTHON_PATH dans .env.\n" .
            "Téléchargement : https://www.python.org/downloads/"
        );
    }
}
