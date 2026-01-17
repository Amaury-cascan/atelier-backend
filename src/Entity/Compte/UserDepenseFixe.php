<?php

namespace App\Entity\Compte;

use App\Repository\Compte\UserDepenseFixeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserDepenseFixeRepository::class)]
class UserDepenseFixe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column]
    private ?int $montant = null;

    #[ORM\ManyToOne(inversedBy: 'userDepenseFixes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?UserMois $userMois = null;

    #[ORM\Column]
    private ?bool $isDepenseCommune = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getMontant(): ?int
    {
        return $this->montant;
    }

    public function setMontant(int $montant): static
    {
        $this->montant = $montant;

        return $this;
    }

    public function getUserMois(): ?UserMois
    {
        return $this->userMois;
    }

    public function setUserMois(?UserMois $userMois): static
    {
        $this->userMois = $userMois;

        return $this;
    }

    public function isDepenseCommune(): ?bool
    {
        return $this->isDepenseCommune;
    }

    public function setIsDepenseCommune(bool $isDepenseCommune): static
    {
        $this->isDepenseCommune = $isDepenseCommune;

        return $this;
    }
}
