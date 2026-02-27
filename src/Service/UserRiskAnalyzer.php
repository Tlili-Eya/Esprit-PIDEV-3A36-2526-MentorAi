<?php

namespace App\Service;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calculates a trust score (0–100) and risk level (LOW/MEDIUM/HIGH)
 * for each user based on behavioural and profile signals.
 */
final class UserRiskAnalyzer
{
    /** Disposable / temporary email providers to flag as suspicious */
    private const SUSPICIOUS_DOMAINS = [
        'mailinator.com', 'tempmail.com', 'guerrillamail.com', 'throwam.com',
        'sharklasers.com', 'guerrillamail.info', 'spam4.me', 'trashmail.com',
        'yopmail.com', 'dispostable.com', 'fakeinbox.com', 'maildrop.cc',
        'mailnull.com', 'spamgourmet.com', 'getairmail.com', 'trashmail.net',
    ];

    public function __construct(
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {}

    /**
     * Analyse a single user and update its risk fields (no flush).
     */
    public function analyze(Utilisateur $user): void
    {
        $score = 100.0;
        $flaggedDuplicate = false;
        $allUsers = $this->utilisateurRepository->findAll();

        // ── Rule 1: Too many failed login attempts ────────────────────────
        if ($user->getLoginAttempts() > 5) {
            $score -= 15;
        }

        // ── Rule 2: Suspicious / disposable email domain ─────────────────
        $email  = $user->getEmail() ?? '';
        $atPos  = strrpos($email, '@');
        $domain = $atPos !== false ? strtolower(substr($email, $atPos + 1)) : '';
        if ($domain && in_array($domain, self::SUSPICIOUS_DOMAINS, true)) {
            $score -= 20;
        }

        // ── Rule 3: No profile photo ──────────────────────────────────────
        if (!$user->getPdpUrl()) {
            $score -= 5;
        }

        // ── Rule 4: Same registration IP as another account ───────────────
        $ip = $user->getRegistrationIp();
        if ($ip) {
            foreach ($allUsers as $other) {
                if ($other->getId() === $user->getId()) {
                    continue;
                }
                if ($other->getRegistrationIp() === $ip) {
                    $score -= 25;
                    $flaggedDuplicate = true;
                    break; // penalise only once
                }
            }
        }

        $score = max(0.0, $score);

        $riskLevel = match (true) {
            $score >= 80 => 'LOW',
            $score >= 50 => 'MEDIUM',
            default      => 'HIGH',
        };

        $user->setTrustScore($score)
             ->setRiskLevel($riskLevel)
             ->setFlaggedDuplicate($flaggedDuplicate);
    }

    /**
     * Analyse all users, flush, and return count per risk level.
     *
     * @return array{LOW: int, MEDIUM: int, HIGH: int}
     */
    public function analyzeAll(): array
    {
        $counts = ['LOW' => 0, 'MEDIUM' => 0, 'HIGH' => 0];

        foreach ($this->utilisateurRepository->findAll() as $user) {
            $this->analyze($user);
            $counts[$user->getRiskLevel()]++;
        }

        $this->entityManager->flush();

        return $counts;
    }
}
