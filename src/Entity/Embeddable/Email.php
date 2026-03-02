<?php

namespace App\Entity\Embeddable;

use Doctrine\ORM\Mapping as ORM;

/**
 * Value Object for an Email Address.
 */
#[ORM\Embeddable]
class Email
{
    #[ORM\Column(name: 'email', length: 255, unique: true)]
    private string $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
