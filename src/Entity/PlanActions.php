<?php

namespace App\Entity;

use App\Enum\Statut;
use App\Enum\CategorieSortie;
use App\Repository\PlanActionsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection; 



#[ORM\Entity(repositoryClass: PlanActionsRepository::class)]
#[ORM\Table(name: 'plan_actions')]
#[ORM\HasLifecycleCallbacks]
class PlanActions
{
    /** @var int|null */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    private ?Utilisateur $etudiant = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "La décision est obligatoire")]
    #[Assert\Length(
        min: 5,
        max: 200,
        minMessage: "La décision doit contenir au moins {{ limit }} caractères",
        maxMessage: "La décision ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $decision = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "La description est obligatoire")]
    #[Assert\Length(
        min: 10,
        minMessage: "La description doit contenir au moins {{ limit }} caractères"
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotBlank(message: "La date est obligatoire")]
    private ?\DateTimeImmutable $date = null;


    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
    
    #[ORM\Column(type: 'string', enumType: Statut::class)]
    #[Assert\NotNull(message: "Le statut est obligatoire")] 
    private ?Statut $statut = null;

    #[ORM\Column(type: 'string', enumType: CategorieSortie::class, nullable: true)]
    #[Assert\NotNull(message: "La catégorie est obligatoire")]
    private ?CategorieSortie $categorie = null;

    #[ORM\ManyToOne(inversedBy: 'planActions')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?SortieAI $sortieAI = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $feedbackEnseignant = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $feedbackDate = null;

    #[ORM\ManyToOne]
    private ?Utilisateur $feedbackAuteur = null;

    #[ORM\ManyToOne(inversedBy: 'plansCrees')]
    private ?Utilisateur $auteur = null;

 /**
     * @var Collection<int, ReferenceArticle>
     */
    #[ORM\ManyToMany(targetEntity: ReferenceArticle::class, inversedBy: 'planActions')]
    #[ORM\JoinTable(name: 'plan_actions_articles')]
    private Collection $articles;

    public function __construct()
    {
        $this->date = new \DateTimeImmutable();
        $this->statut = null;
        $this->categorie = null;
        $this->articles = new ArrayCollection(); 
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEtudiant(): ?Utilisateur
    {
        return $this->etudiant;
    }

    public function setEtudiant(?Utilisateur $etudiant): static
    {
        $this->etudiant = $etudiant;
        return $this;
    }

    public function getDecision(): ?string
    {
        return $this->decision;
    }

    public function setDecision(string $decision): static
    {
        $this->decision = $decision;
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

    // GETTER/SETTER POUR date (NOUVEAU)
    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    protected function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getStatut(): ?Statut
    {
        return $this->statut;
    }

    public function setStatut(Statut $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getCategorie(): ?CategorieSortie
    {
        return $this->categorie;
    }

    public function setCategorie(?CategorieSortie $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getSortieAI(): ?SortieAI
    {
        return $this->sortieAI;
    }

    public function setSortieAI(?SortieAI $sortieAI): static
    {
        $this->sortieAI = $sortieAI;
        return $this;
    }

    public function getFeedbackEnseignant(): ?string
    {
        return $this->feedbackEnseignant;
    }

    public function setFeedbackEnseignant(?string $feedbackEnseignant): static
    {
        $this->feedbackEnseignant = $feedbackEnseignant;
        if ($feedbackEnseignant) {
            $this->feedbackDate = new \DateTimeImmutable();
        }
        return $this;
    }

    public function getFeedbackDate(): ?\DateTimeImmutable
    {
        return $this->feedbackDate;
    }

    protected function setFeedbackDate(?\DateTimeImmutable $feedbackDate): static
    {
        $this->feedbackDate = $feedbackDate;
        return $this;
    }

    public function getFeedbackAuteur(): ?Utilisateur
    {
        return $this->feedbackAuteur;
    }

    public function setFeedbackAuteur(?Utilisateur $feedbackAuteur): static
    {
        $this->feedbackAuteur = $feedbackAuteur;
        return $this;
    }

    public function getCategorieNom(): string
    {
        return ($this->categorie ? $this->categorie->value : '');
    }
    
    public function getStatutNom(): string
    {
        return ($this->statut ? $this->statut->value : '');
    }
  /**
     * @return Collection<int, ReferenceArticle>
     */
    public function getArticles(): Collection
    {
        return $this->articles;
    }

    public function addArticle(ReferenceArticle $article): static
    {
        if (!$this->articles->contains($article)) {
            $this->articles->add($article);
        }

        return $this;
    }

    public function removeArticle(ReferenceArticle $article): static
    {
        $this->articles->removeElement($article);

        return $this;
    }

    public function getAuteur(): ?Utilisateur
    {
        return $this->auteur;
    }

    public function setAuteur(?Utilisateur $auteur): static
    {
        $this->auteur = $auteur;
        return $this;
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->date === null) {
            $this->date = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return $this->decision ?? 'Plan d\'action sans titre';
    }
}