<?php

namespace App\Entity\Compte;

use App\Repository\Compte\EnveloppeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EnveloppeRepository::class)]
class Enveloppe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column]
    private ?int $montant = null;

    #[ORM\Column]
    private ?int $pourcentage = null;

    #[ORM\ManyToOne(inversedBy: 'enveloppes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?UserMois $userMois = null;

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

    public function getPourcentage(): ?int
    {
        return $this->pourcentage;
    }

    public function setPourcentage(int $pourcentage): static
    {
        $this->pourcentage = $pourcentage;

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
}
