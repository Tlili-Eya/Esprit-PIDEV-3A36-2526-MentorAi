<?php

namespace App\Entity;

use App\Repository\PlanningEtudeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanningEtudeRepository::class)]
class PlanningEtude
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $titre_p = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $date_seance = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure_debut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTime $heure_fin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $matiere = null;

    #[ORM\Column(length: 255)]
    private ?string $type_activite = null;

    #[ORM\Column(length: 255)]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes_pers = null;

    #[ORM\Column(nullable: true)]
    private ?int $duree_prevue = null;

    #[ORM\Column(nullable: true)]
    private ?int $duree_reelle = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $etat = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $couleur_activite = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateCreation = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $dateModification = null;

    #[ORM\ManyToOne]
    private ?Utilisateur $Utilisateur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitreP(): ?string
    {
        return $this->titre_p;
    }

    public function setTitreP(?string $titre_p): static
    {
        $this->titre_p = $titre_p;

        return $this;
    }

    public function getDateSeance(): ?\DateTime
    {
        return $this->date_seance;
    }

    public function setDateSeance(\DateTime $date_seance): static
    {
        $this->date_seance = $date_seance;

        return $this;
    }

    public function getHeureDebut(): ?\DateTime
    {
        return $this->heure_debut;
    }

    public function setHeureDebut(?\DateTime $heure_debut): static
    {
        $this->heure_debut = $heure_debut;

        return $this;
    }

    public function getHeureFin(): ?\DateTime
    {
        return $this->heure_fin;
    }

    public function setHeureFin(?\DateTime $heure_fin): static
    {
        $this->heure_fin = $heure_fin;

        return $this;
    }

    public function getMatiere(): ?string
    {
        return $this->matiere;
    }

    public function setMatiere(?string $matiere): static
    {
        $this->matiere = $matiere;

        return $this;
    }

    public function getTypeActivite(): ?string
    {
        return $this->type_activite;
    }

    public function setTypeActivite(string $type_activite): static
    {
        $this->type_activite = $type_activite;

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

    public function getNotesPers(): ?string
    {
        return $this->notes_pers;
    }

    public function setNotesPers(?string $notes_pers): static
    {
        $this->notes_pers = $notes_pers;

        return $this;
    }

    public function getDureePrevue(): ?int
    {
        return $this->duree_prevue;
    }

    public function setDureePrevue(?int $duree_prevue): static
    {
        $this->duree_prevue = $duree_prevue;

        return $this;
    }

    public function getDureeReelle(): ?int
    {
        return $this->duree_reelle;
    }

    public function setDureeReelle(?int $duree_reelle): static
    {
        $this->duree_reelle = $duree_reelle;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(?string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getCouleurActivite(): ?string
    {
        return $this->couleur_activite;
    }

    public function setCouleurActivite(?string $couleur_activite): static
    {
        $this->couleur_activite = $couleur_activite;

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

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->Utilisateur;
    }

    public function setUtilisateur(?Utilisateur $Utilisateur): static
    {
        $this->Utilisateur = $Utilisateur;

        return $this;
    }
}
