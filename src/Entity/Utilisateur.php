<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Serializer\Attribute\Ignore;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $mdp = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $pdp_url = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $date_inscription = null;

    #[ORM\Column(length: 255)]
    private ?string $role = null;

    // ✅ FIX SECURITY — exclus de la sérialisation JSON/API/logs
    #[ORM\Column(length: 255, nullable: true)]
    #[Ignore]
    private ?string $resetToken = null;

    // ✅ FIX SECURITY — exclus de la sérialisation JSON/API/logs
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Ignore]
    private ?\DateTimeInterface $resetTokenExpiresAt = null;

    #[ORM\Column(length: 20, options: ['default' => 'actif'])]
    private ?string $status = 'actif';

    // ── AI Risk Monitoring ───────────────────────────────────────────────
    #[ORM\Column(type: 'float', options: ['default' => 100])]
    private float $trustScore = 100.0;

    #[ORM\Column(length: 10, options: ['default' => 'LOW'])]
    private string $riskLevel = 'LOW';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $flaggedDuplicate = false;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $loginAttempts = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $registrationIp = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $aiVerdict = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferences = [];

    /**
     * @var Collection<int, CategorieArticle>
     */
    #[ORM\OneToMany(targetEntity: CategorieArticle::class, mappedBy: 'auteur')]
    private Collection $categorieArticles;

    /**
     * @var Collection<int, ReferenceArticle>
     */
    #[ORM\OneToMany(targetEntity: ReferenceArticle::class, mappedBy: 'auteur')]
    private Collection $referenceArticles;

    /**
     * @var Collection<int, Projet>
     */
    #[ORM\OneToMany(targetEntity: Projet::class, mappedBy: 'utilisateur')]
    private Collection $projets;

    /**
     * @var Collection<int, Parcours>
     */
    #[ORM\OneToMany(targetEntity: Parcours::class, mappedBy: 'utilisateur')]
    private Collection $parcours;

    /**
     * @var Collection<int, Feedback>
     */
    #[ORM\OneToMany(targetEntity: Feedback::class, mappedBy: 'utilisateur', orphanRemoval: true, cascade: ['persist'])]
    private Collection $feedback;

    /**
     * @var Collection<int, Objectif>
     */
    #[ORM\OneToMany(targetEntity: Objectif::class, mappedBy: 'utilisateur', orphanRemoval: true, cascade: ['persist'])]
    private Collection $objectifs;

    public function __construct()
    {
        $this->categorieArticles = new ArrayCollection();
        $this->referenceArticles = new ArrayCollection();
        $this->projets = new ArrayCollection();
        $this->parcours = new ArrayCollection();
        $this->feedback = new ArrayCollection();
        $this->objectifs = new ArrayCollection();
    }

    // ==================== MÉTHODES POUR UserInterface ====================

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = [];

        if ($this->role) {
            $roles[] = 'ROLE_' . strtoupper($this->role);
        }

        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function eraseCredentials(): void
    {
        // Effacer les données sensibles temporaires si nécessaire
    }

    public function getPassword(): ?string
    {
        return $this->mdp;
    }

    // ==================== GETTERS/SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getMdp(): ?string
    {
        return $this->mdp;
    }

    public function setMdp(string $mdp): static
    {
        $this->mdp = $mdp;
        return $this;
    }

    public function getPdpUrl(): ?string
    {
        return $this->pdp_url;
    }

    public function setPdpUrl(?string $pdp_url): static
    {
        $this->pdp_url = $pdp_url;
        return $this;
    }

    public function getDateInscription(): ?\DateTime
    {
        return $this->date_inscription;
    }

    public function setDateInscription(?\DateTime $date_inscription): static
    {
        $this->date_inscription = $date_inscription;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    // ==================== RESET PASSWORD ====================

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    // ✅ FIX SECURITY — SensitiveParameter masque la valeur dans les stack traces
    public function setResetToken(#[\SensitiveParameter] ?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiresAt;
    }

    // ✅ FIX SECURITY — SensitiveParameter masque la valeur dans les stack traces
    protected function setResetTokenExpiresAt(#[\SensitiveParameter] ?\DateTimeInterface $resetTokenExpiresAt): static
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;
        return $this;
    }

    public function updateResetTokenExpiresAt(#[\SensitiveParameter] ?\DateTimeInterface $resetTokenExpiresAt): static
    {
        return $this->setResetTokenExpiresAt($resetTokenExpiresAt);
    }

    public function isResetTokenValid(): bool
    {
        if (!$this->resetToken || !$this->resetTokenExpiresAt) {
            return false;
        }
        return new \DateTime() < $this->resetTokenExpiresAt;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->status === 'actif';
    }

    // ── AI Risk Monitoring — Getters/Setters ─────────────────────────────

    public function getTrustScore(): float { return $this->trustScore; }
    public function setTrustScore(float $v): static { $this->trustScore = $v; return $this; }

    public function getRiskLevel(): string { return $this->riskLevel; }
    public function setRiskLevel(string $v): static { $this->riskLevel = $v; return $this; }

    public function isFlaggedDuplicate(): bool { return $this->flaggedDuplicate; }
    public function setFlaggedDuplicate(bool $v): static { $this->flaggedDuplicate = $v; return $this; }

    public function getLoginAttempts(): int { return $this->loginAttempts; }
    public function setLoginAttempts(int $v): static { $this->loginAttempts = $v; return $this; }
    public function incrementLoginAttempts(): static { $this->loginAttempts++; return $this; }
    public function resetLoginAttempts(): static { $this->loginAttempts = 0; return $this; }

    public function getLastLogin(): ?\DateTimeInterface { return $this->lastLogin; }
    protected function setLastLogin(?\DateTimeInterface $v): static { $this->lastLogin = $v; return $this; }
    public function markLastLogin(?\DateTimeInterface $v = null): static
    {
        return $this->setLastLogin($v ?? new \DateTimeImmutable());
    }

    public function getRegistrationIp(): ?string { return $this->registrationIp; }
    public function setRegistrationIp(?string $v): static { $this->registrationIp = $v; return $this; }

    public function getAiVerdict(): ?string { return $this->aiVerdict; }
    public function setAiVerdict(?string $v): static { $this->aiVerdict = $v; return $this; }

    public function getPreferences(): array { return $this->preferences ?? []; }
    public function setPreferences(?array $preferences): static { $this->preferences = $preferences; return $this; }

    // ==================== RELATIONS ====================

    /**
     * @return Collection<int, CategorieArticle>
     */
    public function getCategorieArticles(): Collection
    {
        return $this->categorieArticles;
    }

    public function addCategorieArticle(CategorieArticle $categorieArticle): static
    {
        if (!$this->categorieArticles->contains($categorieArticle)) {
            $this->categorieArticles->add($categorieArticle);
            $categorieArticle->setAuteur($this);
        }
        return $this;
    }

    public function removeCategorieArticle(CategorieArticle $categorieArticle): static
    {
        if ($this->categorieArticles->removeElement($categorieArticle)) {
            if ($categorieArticle->getAuteur() === $this) {
                $categorieArticle->setAuteur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ReferenceArticle>
     */
    public function getReferenceArticles(): Collection
    {
        return $this->referenceArticles;
    }

    public function addReferenceArticle(ReferenceArticle $referenceArticle): static
    {
        if (!$this->referenceArticles->contains($referenceArticle)) {
            $this->referenceArticles->add($referenceArticle);
            $referenceArticle->setAuteur($this);
        }
        return $this;
    }

    public function removeReferenceArticle(ReferenceArticle $referenceArticle): static
    {
        if ($this->referenceArticles->removeElement($referenceArticle)) {
            if ($referenceArticle->getAuteur() === $this) {
                $referenceArticle->setAuteur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Projet>
     */
    public function getProjets(): Collection
    {
        return $this->projets;
    }

    public function addProjet(Projet $projet): static
    {
        if (!$this->projets->contains($projet)) {
            $this->projets->add($projet);
            $projet->setUtilisateur($this);
        }
        return $this;
    }

    public function removeProjet(Projet $projet): static
    {
        if ($this->projets->removeElement($projet)) {
            if ($projet->getUtilisateur() === $this) {
                $projet->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Parcours>
     */
    public function getParcours(): Collection
    {
        return $this->parcours;
    }

    public function addParcours(Parcours $parcours): static
    {
        if (!$this->parcours->contains($parcours)) {
            $this->parcours->add($parcours);
            $parcours->setUtilisateur($this);
        }
        return $this;
    }

    public function removeParcours(Parcours $parcours): static
    {
        if ($this->parcours->removeElement($parcours)) {
            if ($parcours->getUtilisateur() === $this) {
                $parcours->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Feedback>
     */
    public function getFeedback(): Collection
    {
        return $this->feedback;
    }

    public function addFeedback(Feedback $feedback): static
    {
        if (!$this->feedback->contains($feedback)) {
            $this->feedback->add($feedback);
            $feedback->setUtilisateur($this);
        }
        return $this;
    }

    public function removeFeedback(Feedback $feedback): static
    {
        if ($this->feedback->removeElement($feedback)) {
            if ($feedback->getUtilisateur() === $this) {
                $feedback->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Objectif>
     */
    public function getObjectifs(): Collection
    {
        return $this->objectifs;
    }

    public function addObjectif(Objectif $objectif): static
    {
        if (!$this->objectifs->contains($objectif)) {
            $this->objectifs->add($objectif);
            $objectif->setUtilisateur($this);
        }
        return $this;
    }

    public function removeObjectif(Objectif $objectif): static
    {
        if ($this->objectifs->removeElement($objectif)) {
            if ($objectif->getUtilisateur() === $this) {
                $objectif->setUtilisateur(null);
            }
        }
        return $this;
    }
}