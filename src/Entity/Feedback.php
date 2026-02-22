<?php

namespace App\Entity;

use App\Repository\FeedbackRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FeedbackRepository::class)]
class Feedback
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

   #[ORM\Column(type: Types::TEXT)]
   private ?string $contenu = null;

    #[ORM\Column]
    private ?int $note = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $datefeedback = null;

    #[ORM\Column(length: 255)]
    private ?string $typefeedback = null;

    #[ORM\Column(length: 255)]
    private ?string $etatfeedback = null;

    #[ORM\OneToOne(inversedBy: 'feedback', cascade: ['persist', 'remove'])]
    private ?Traitement $traitement = null;

    #[ORM\ManyToOne(inversedBy: 'feedback')]
    private ?Utilisateur $utilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getNote(): ?int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getDatefeedback(): ?\DateTime
    {
        return $this->datefeedback;
    }

    public function setDatefeedback(\DateTime $datefeedback): static
    {
        $this->datefeedback = $datefeedback;

        return $this;
    }

    public function getTypefeedback(): ?string
    {
        return $this->typefeedback;
    }

    public function setTypefeedback(string $typefeedback): static
    {
        $this->typefeedback = $typefeedback;

        return $this;
    }

    public function getEtatfeedback(): ?string
    {
        return $this->etatfeedback;
    }

    public function setEtatfeedback(string $etatfeedback): static
    {
        $this->etatfeedback = $etatfeedback;

        return $this;
    }

    public function getTraitement(): ?Traitement
    {
        return $this->traitement;
    }

    public function setTraitement(?Traitement $traitement): static
    {
        $this->traitement = $traitement;

        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }
}
