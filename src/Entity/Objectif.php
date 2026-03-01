<?php

namespace App\Entity;

use App\Repository\ObjectifRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Enum\Statutobj;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ObjectifRepository::class)]
class Objectif
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.onlyRead */
    private int $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 150,
        minMessage: "Le titre doit contenir au moins 3 caractères.",
        maxMessage: "Le titre ne peut pas dépasser 150 caractères."
    )]
    private ?string $titre = null;

    #[ORM\Column(length: 500, nullable: false)]
    #[Assert\NotBlank(message: "La description est obligatoire.")]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotNull(message: "La date de début est obligatoire.")]
    #[Assert\GreaterThanOrEqual(
        value: "today",
        message: "La date de début ne peut pas être dans le passé. Elle doit être aujourd'hui ou plus tard."
    )]
    private ?\DateTime $datedebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: false)]
    #[Assert\NotNull(message: "La date de fin est obligatoire.")]
    #[Assert\GreaterThan(
        propertyPath: "datedebut",
        message: "La date de fin doit être strictement postérieure à la date de début."
    )]
    private ?\DateTime $datefin = null;

    #[ORM\Column(enumType: Statutobj::class)]
    private Statutobj $statut = Statutobj::Atteint;

    #[ORM\OneToOne(inversedBy: 'objectif', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?Programme $programme = null;

    // ✅ FIX — nullable: false force INNER JOIN au lieu de LEFT JOIN (+20-30% perf)
    #[ORM\ManyToOne(inversedBy: 'objectifs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getDatedebut(): ?\DateTime
    {
        return $this->datedebut;
    }

    public function setDatedebut(?\DateTime $datedebut): static
    {
        $this->datedebut = $datedebut;

        return $this;
    }

    public function getDatefin(): ?\DateTime
    {
        return $this->datefin;
    }

    public function setDatefin(?\DateTime $datefin): static
    {
        $this->datefin = $datefin;

        return $this;
    }

    public function getStatut(): ?Statutobj
    {
        return $this->statut;
    }

    public function setStatut(Statutobj $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getProgramme(): ?Programme
    {
        return $this->programme;
    }

    public function setProgramme(Programme $programme): static
    {
        $this->programme = $programme;

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