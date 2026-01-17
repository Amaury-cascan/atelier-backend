<?php

namespace App\Entity\Compte;

use App\Entity\User;
use App\Repository\Compte\UserMoisRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserMoisRepository::class)]
class UserMois
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $salaire = null;

    #[ORM\Column(nullable: true)]
    private ?int $tauxEnveloppe = null;

    #[ORM\Column(nullable: true)]
    private ?int $epargne = null;

    #[ORM\ManyToOne(inversedBy: 'userMois')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $currentUser = null;

    #[ORM\ManyToOne(inversedBy: 'userMois')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Exercice $exercice = null;

    /**
     * @var Collection<int, UserDepenseFixe>
     */
    #[ORM\OneToMany(targetEntity: UserDepenseFixe::class, mappedBy: 'userMois', orphanRemoval: true)]
    private Collection $userDepenseFixes;

    /**
     * @var Collection<int, Enveloppe>
     */
    #[ORM\OneToMany(targetEntity: Enveloppe::class, mappedBy: 'userMois', orphanRemoval: true)]
    private Collection $enveloppes;

    public function __construct()
    {
        $this->userDepenseFixes = new ArrayCollection();
        $this->enveloppes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSalaire(): ?int
    {
        return $this->salaire;
    }

    public function setSalaire(?int $salaire): static
    {
        $this->salaire = $salaire;

        return $this;
    }

    public function getTauxEnveloppe(): ?int
    {
        return $this->tauxEnveloppe;
    }

    public function setTauxEnveloppe(?int $tauxEnveloppe): static
    {
        $this->tauxEnveloppe = $tauxEnveloppe;

        return $this;
    }

    public function getEpargne(): ?int
    {
        return $this->epargne;
    }

    public function setEpargne(?int $epargne): static
    {
        $this->epargne = $epargne;

        return $this;
    }

    public function getCurrentUser(): ?User
    {
        return $this->currentUser;
    }

    public function setCurrentUser(?User $currentUser): static
    {
        $this->currentUser = $currentUser;

        return $this;
    }

    public function getExercice(): ?Exercice
    {
        return $this->exercice;
    }

    public function setExercice(?Exercice $exercice): static
    {
        $this->exercice = $exercice;

        return $this;
    }

    /**
     * @return Collection<int, UserDepenseFixe>
     */
    public function getUserDepenseFixes(): Collection
    {
        return $this->userDepenseFixes;
    }

    public function addUserDepenseFix(UserDepenseFixe $userDepenseFix): static
    {
        if (!$this->userDepenseFixes->contains($userDepenseFix)) {
            $this->userDepenseFixes->add($userDepenseFix);
            $userDepenseFix->setUserMois($this);
        }

        return $this;
    }

    public function removeUserDepenseFix(UserDepenseFixe $userDepenseFix): static
    {
        if ($this->userDepenseFixes->removeElement($userDepenseFix)) {
            // set the owning side to null (unless already changed)
            if ($userDepenseFix->getUserMois() === $this) {
                $userDepenseFix->setUserMois(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Enveloppe>
     */
    public function getEnveloppes(): Collection
    {
        return $this->enveloppes;
    }

    public function addEnveloppe(Enveloppe $enveloppe): static
    {
        if (!$this->enveloppes->contains($enveloppe)) {
            $this->enveloppes->add($enveloppe);
            $enveloppe->setUserMois($this);
        }

        return $this;
    }

    public function removeEnveloppe(Enveloppe $enveloppe): static
    {
        if ($this->enveloppes->removeElement($enveloppe)) {
            // set the owning side to null (unless already changed)
            if ($enveloppe->getUserMois() === $this) {
                $enveloppe->setUserMois(null);
            }
        }

        return $this;
    }
}
