<?php

namespace App\Entity;

use App\Enum\Medaille;
use App\Repository\ProgrammeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProgrammeRepository::class)]
class Programme
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
     /** @phpstan-ignore property.onlyRead */
    private int $id;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dategeneration = null;
    
    #[ORM\Column(enumType: Medaille::class, nullable: true)]
private ?Medaille $meilleureMedaille = null;

#[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
private int $scorePourcentage = 0;

    #[ORM\OneToOne(mappedBy: 'programme', cascade: ['persist', 'remove'], orphanRemoval:true)]
    private ?Objectif $objectif = null;

    /**
     * @var Collection<int, Motivation>
     */
    #[ORM\OneToMany(targetEntity: Motivation::class, mappedBy: 'programme', orphanRemoval:true)]
    private Collection $motivation;

    /**
     * @var Collection<int, Tache>
     */
    #[ORM\OneToMany(targetEntity: Tache::class, mappedBy: 'programme', orphanRemoval:true)]
    private Collection $tache;

    public function __construct()
    {
        $this->motivation = new ArrayCollection();
        $this->tache = new ArrayCollection();
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
    public function getMeilleureMedaille(): ?Medaille
{
    return $this->meilleureMedaille;
}

public function setMeilleureMedaille(?Medaille $meilleureMedaille): static
{
    $this->meilleureMedaille = $meilleureMedaille;
    return $this;
}
public function getScorePourcentage(): int
{
    return $this->scorePourcentage;
}

public function setScorePourcentage(int $scorePourcentage): static
{
    $this->scorePourcentage = $scorePourcentage;
    return $this;
}

    public function getDategeneration(): ?\DateTime
    {
        return $this->dategeneration;
    }

    public function setDategeneration(\DateTime $dategeneration): static
    {
        $this->dategeneration = $dategeneration;

        return $this;
    }

    public function getObjectif(): ?Objectif
    {
        return $this->objectif;
    }

    public function setObjectif(Objectif $objectif): static
    {
        // set the owning side of the relation if necessary
        if ($objectif->getProgramme() !== $this) {
            $objectif->setProgramme($this);
        }

        $this->objectif = $objectif;

        return $this;
    }

    /**
     * @return Collection<int, Motivation>
     */
    public function getMotivation(): Collection
    {
        return $this->motivation;
    }

    public function addMotivation(Motivation $motivation): static
    {
        if (!$this->motivation->contains($motivation)) {
            $this->motivation->add($motivation);
            $motivation->setProgramme($this);
        }

        return $this;
    }

    public function removeMotivation(Motivation $motivation): static
    {
        if ($this->motivation->removeElement($motivation)) {
            // set the owning side to null (unless already changed)
            if ($motivation->getProgramme() === $this) {
                $motivation->setProgramme(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Tache>
     */
    public function getTache(): Collection
    {
        return $this->tache;
    }

    public function addTache(Tache $tache): static
    {
        if (!$this->tache->contains($tache)) {
            $this->tache->add($tache);
            $tache->setProgramme($this);
        }

        return $this;
    }

    public function removeTache(Tache $tache): static
    {
        if ($this->tache->removeElement($tache)) {
            // set the owning side to null (unless already changed)
            if ($tache->getProgramme() === $this) {
                $tache->setProgramme(null);
            }
        }

        return $this;
    }
}
