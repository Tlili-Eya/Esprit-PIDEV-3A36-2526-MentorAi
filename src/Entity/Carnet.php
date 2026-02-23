<?php

namespace App\Entity;

use App\Repository\CarnetRepository;
use App\Entity\PlanningEtude;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CarnetRepository::class)]
class Carnet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true, columnDefinition: 'LONGTEXT')]
    private ?string $contenu = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $attachments = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $coleur = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $visibilite = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateModification = null;

    /**
    * @var Collection<int, PlanningEtude>
     */
    #[ORM\ManyToMany(targetEntity: PlanningEtude::class)]
    private Collection $planningEtude;

    #[ORM\ManyToOne]
    private ?Utilisateur $Utilisateurs = null;

    public function __construct()
    {
        $this->planningEtude = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(?string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getAttachments(): ?array
    {
        return $this->attachments;
    }

    public function setAttachments(?array $attachments): static
    {
        $this->attachments = $attachments;

        return $this;
    }

    public function getColeur(): ?string
    {
        return $this->coleur;
    }

    public function setColeur(?string $coleur): static
    {
        $this->coleur = $coleur;

        return $this;
    }

    public function getVisibilite(): ?string
    {
        return $this->visibilite;
    }

    public function setVisibilite(?string $visibilite): static
    {
        $this->visibilite = $visibilite;

        return $this;
    }

    public function getDateCreation(): ?\DateTime
    {
        return $this->dateCreation;
    }

    public function setDateCreation(?\DateTime $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getDateModification(): ?\DateTime
    {
        return $this->dateModification;
    }

    public function setDateModification(?\DateTime $dateModification): static
    {
        $this->dateModification = $dateModification;

        return $this;
    }

    /**
     * @return Collection<int, PlanningEtude>
     */
    public function getPlanningEtude(): Collection
    {
        return $this->planningEtude;
    }

    public function addPlanningEtude(PlanningEtude $planningEtude): static
    {
        if (!$this->planningEtude->contains($planningEtude)) {
            $this->planningEtude->add($planningEtude);
        }

        return $this;
    }

    public function removePlanningEtude(PlanningEtude $planningEtude): static
    {
        $this->planningEtude->removeElement($planningEtude);

        return $this;
    }

    public function getUtilisateurs(): ?Utilisateur
    {
        return $this->Utilisateurs;
    }

    public function setUtilisateurs(?Utilisateur $Utilisateurs): static
    {
        $this->Utilisateurs = $Utilisateurs;

        return $this;
    }
}
