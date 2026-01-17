<?php

namespace App\Entity\Compte;

use App\Repository\Compte\ExerciceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ExerciceRepository::class)]
class Exercice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $mois = null;

    #[ORM\Column(nullable: true)]
    private ?int $montantTotal = null;

    #[ORM\Column(nullable: true)]
    private ?int $montantAide = null;

    /**
     * @var Collection<int, DepenseFixe>
     */
    #[ORM\OneToMany(targetEntity: DepenseFixe::class, mappedBy: 'exercice', orphanRemoval: true)]
    private Collection $depenseFixes;

    /**
     * @var Collection<int, UserMois>
     */
    #[ORM\OneToMany(targetEntity: UserMois::class, mappedBy: 'exercice', orphanRemoval: true)]
    private Collection $userMois;

    public function __construct()
    {
        $this->depenseFixes = new ArrayCollection();
        $this->userMois = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMois(): ?\DateTimeInterface
    {
        return $this->mois;
    }

    public function setMois(\DateTimeInterface $mois): static
    {
        $this->mois = $mois;

        return $this;
    }

    public function getMontantTotal(): ?int
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(?int $montantTotal): static
    {
        $this->montantTotal = $montantTotal;

        return $this;
    }

    public function getMontantAide(): ?int
    {
        return $this->montantAide;
    }

    public function setMontantAide(?int $montantAide): static
    {
        $this->montantAide = $montantAide;

        return $this;
    }

    /**
     * @return Collection<int, DepenseFixe>
     */
    public function getDepenseFixes(): Collection
    {
        return $this->depenseFixes;
    }

    public function addDepenseFix(DepenseFixe $depenseFix): static
    {
        if (!$this->depenseFixes->contains($depenseFix)) {
            $this->depenseFixes->add($depenseFix);
            $depenseFix->setExercice($this);
        }

        return $this;
    }

    public function removeDepenseFix(DepenseFixe $depenseFix): static
    {
        if ($this->depenseFixes->removeElement($depenseFix)) {
            // set the owning side to null (unless already changed)
            if ($depenseFix->getExercice() === $this) {
                $depenseFix->setExercice(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, UserMois>
     */
    public function getUserMois(): Collection
    {
        return $this->userMois;
    }

    public function addUserMoi(UserMois $userMoi): static
    {
        if (!$this->userMois->contains($userMoi)) {
            $this->userMois->add($userMoi);
            $userMoi->setExercice($this);
        }

        return $this;
    }

    public function removeUserMoi(UserMois $userMoi): static
    {
        if ($this->userMois->removeElement($userMoi)) {
            // set the owning side to null (unless already changed)
            if ($userMoi->getExercice() === $this) {
                $userMoi->setExercice(null);
            }
        }

        return $this;
    }
}
