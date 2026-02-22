<?php

namespace App\Entity;

use App\Repository\ChatMessageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChatMessageRepository::class)]
class ChatMessage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ProfilApprentissage::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProfilApprentissage $profilApprentissage = null;

    #[ORM\Column(length: 50)]
    private ?string $role = null; // 'user' or 'assistant'

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenu = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null; // Store format, timestamp, learning time, etc.

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $startInteractionTime = null; // For tracking learning time

    #[ORM\Column(nullable: true)]
    private ?int $learningDurationSeconds = null; // Time to understand content

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfilApprentissage(): ?ProfilApprentissage
    {
        return $this->profilApprentissage;
    }

    public function setProfilApprentissage(?ProfilApprentissage $profilApprentissage): static
    {
        $this->profilApprentissage = $profilApprentissage;
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

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getStartInteractionTime(): ?\DateTime
    {
        return $this->startInteractionTime;
    }

    public function setStartInteractionTime(?\DateTime $startInteractionTime): static
    {
        $this->startInteractionTime = $startInteractionTime;
        return $this;
    }

    public function getLearningDurationSeconds(): ?int
    {
        return $this->learningDurationSeconds;
    }

    public function setLearningDurationSeconds(?int $learningDurationSeconds): static
    {
        $this->learningDurationSeconds = $learningDurationSeconds;
        return $this;
    }
}
