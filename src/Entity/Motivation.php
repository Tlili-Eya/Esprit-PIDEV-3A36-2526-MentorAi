<?php

namespace App\Entity;

use App\Repository\MotivationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MotivationRepository::class)]
class Motivation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    /** @phpstan-ignore property.onlyRead */
    private int $id;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTime $dategeneratiomm = null;

    #[ORM\Column(length: 999, nullable: true)]
    private ?string $messagemotivant = null;

    #[ORM\ManyToOne(inversedBy: 'motivation')]
    private ?Programme $programme = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDategeneratiomm(): ?\DateTime
    {
        return $this->dategeneratiomm;
    }

    public function setDategeneratiomm(\DateTime $dategeneratiomm): static
    {
        $this->dategeneratiomm = $dategeneratiomm;

        return $this;
    }

    public function getMessagemotivant(): ?string
    {
        return $this->messagemotivant;
    }

    public function setMessagemotivant(?string $messagemotivant): static
    {
        $this->messagemotivant = $messagemotivant;

        return $this;
    }

    public function getProgramme(): ?Programme
    {
        return $this->programme;
    }

    public function setProgramme(?Programme $programme): static
    {
        $this->programme = $programme;

        return $this;
    }
}