<?php

namespace App\Entity;

use App\Repository\HumeurRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HumeurRepository::class)]
class Humeur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $valeurHumeur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $facteurPrincipal = null;

    #[ORM\Column(nullable: true)]
    private ?int $moyenne7j = null;

    #[ORM\Column(nullable: true)]
    private ?int $moyenne30j = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tendance = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $niveauRisque = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $creeLe = null;

    #[ORM\ManyToOne]
    private ?ProfilApprentissage $profilApprentissage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getValeurHumeur(): ?int
    {
        return $this->valeurHumeur;
    }

    public function setValeurHumeur(?int $valeurHumeur): static
    {
        $this->valeurHumeur = $valeurHumeur;

        return $this;
    }

    public function getFacteurPrincipal(): ?string
    {
        return $this->facteurPrincipal;
    }

    public function setFacteurPrincipal(?string $facteurPrincipal): static
    {
        $this->facteurPrincipal = $facteurPrincipal;

        return $this;
    }

    public function getMoyenne7j(): ?int
    {
        return $this->moyenne7j;
    }

    public function setMoyenne7j(?int $moyenne7j): static
    {
        $this->moyenne7j = $moyenne7j;

        return $this;
    }

    public function getMoyenne30j(): ?int
    {
        return $this->moyenne30j;
    }

    public function setMoyenne30j(?int $moyenne30j): static
    {
        $this->moyenne30j = $moyenne30j;

        return $this;
    }

    public function getTendance(): ?string
    {
        return $this->tendance;
    }

    public function setTendance(?string $tendance): static
    {
        $this->tendance = $tendance;

        return $this;
    }

    public function getNiveauRisque(): ?string
    {
        return $this->niveauRisque;
    }

    public function setNiveauRisque(?string $niveauRisque): static
    {
        $this->niveauRisque = $niveauRisque;

        return $this;
    }

    public function getCreeLe(): ?\DateTime
    {
        return $this->creeLe;
    }

    public function setCreeLe(?\DateTime $creeLe): static
    {
        $this->creeLe = $creeLe;

        return $this;
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
}
