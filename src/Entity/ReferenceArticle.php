<?php

namespace App\Entity;

use App\Repository\ReferenceArticleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: ReferenceArticleRepository::class)]
class ReferenceArticle
{
    /** @var int|null */
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Le titre de l'article est obligatoire")]
    #[Assert\Length(
        min: 5,
        max: 255,
        minMessage: "Le titre doit contenir au moins {{ limit }} caractères",
        maxMessage: "Le titre ne peut pas dépasser {{ limit }} caractères"
    )]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Le contenu de l'article est obligatoire")]
    #[Assert\Length(
        min: 20,
        minMessage: "Le contenu doit contenir au moins {{ limit }} caractères"
    )]
    private ?string $contenu = null;

    #[ORM\ManyToOne(inversedBy: 'referenceArticles')]
    #[Assert\NotNull(message: "La catégorie est obligatoire")]
    private ?CategorieArticle $categorie = null;

    #[ORM\ManyToOne(inversedBy: 'referenceArticles')]
    private ?Utilisateur $auteur = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column]
    private bool $published = false;

    /**
     * @var Collection<int, SortieAI>
     */
    #[ORM\ManyToMany(targetEntity: SortieAI::class, mappedBy: 'articles')]
    private Collection $sortiesAI;

    /**
     * @var Collection<int, PlanActions>
     */
    #[ORM\ManyToMany(targetEntity: PlanActions::class, mappedBy: 'articles')]
    private Collection $planActions;

    public function __construct()
    {
        $this->sortiesAI = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->published = false;
        $this->planActions = new ArrayCollection(); // AJOUT
    }


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

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getCategorie(): ?CategorieArticle
    {
        return $this->categorie;
    }

    public function setCategorie(?CategorieArticle $categorie): static
    {
        $this->categorie = $categorie;

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

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->published;
    }

    public function setPublished(bool $published): static
    {
        $this->published = $published;
        return $this;
    }

    /**
     * @return Collection<int, SortieAI>
     */
    public function getSortiesAI(): Collection
    {
        return $this->sortiesAI;
    }

    public function addSortieAI(SortieAI $sortieAI): static
    {
        if (!$this->sortiesAI->contains($sortieAI)) {
            $this->sortiesAI->add($sortieAI);
            $sortieAI->addArticle($this);
        }

        return $this;
    }

    public function removeSortieAI(SortieAI $sortieAI): static
    {
        if ($this->sortiesAI->removeElement($sortieAI)) {
            $sortieAI->removeArticle($this);
        }

        return $this;
    }
     /**
     * @return Collection<int, PlanActions>
     */
    public function getPlanActions(): Collection
    {
        return $this->planActions;
    }

    public function addPlanAction(PlanActions $planAction): static
    {
        if (!$this->planActions->contains($planAction)) {
            $this->planActions->add($planAction);
            $planAction->addArticle($this);
        }

        return $this;
    }

    public function removePlanAction(PlanActions $planAction): static
    {
        if ($this->planActions->removeElement($planAction)) {
            $planAction->removeArticle($this);
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->titre ?? 'Article sans titre';
    }
}