<?php

namespace App\Entity;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[Groups(['client'])]
class Client extends User
{
    #[ORM\Column(length: 255)]
    private ?string $phoneNumber = null;

    #[ORM\OneToMany(targetEntity: Appointment::class, mappedBy: 'client')]
    private Collection $appointments;

    #[ORM\OneToMany(targetEntity: Review::class, mappedBy: 'client')]
    private Collection $reviews;

    /**
     * @var Collection<int, ClientInformation>
     */
    #[ORM\OneToMany(targetEntity: ClientInformation::class, mappedBy: 'client')]
    private Collection $client;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $Connu = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $etat = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $privacyPolicyAcceptedAt = null;

    public function __construct()
    {
        $this->appointments = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->client = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return parent::getId();
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function getAppointments(): Collection
    {
        return $this->appointments;
    }

    public function addAppointment(Appointment $appointment): static
    {
        if (!$this->appointments->contains($appointment)) {
            $this->appointments->add($appointment);
            $appointment->setClient($this);
        }

        return $this;
    }

    public function removeAppointment(Appointment $appointment): static
    {
        if ($this->appointments->removeElement($appointment)) {
            // set the owning side to null (unless already changed)
            if ($appointment->getClient() === $this) {
                $appointment->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setClient($this);
        }

        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            // set the owning side to null (unless already changed)
            if ($review->getClient() === $this) {
                $review->setClient(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, ClientInformation>
     */
    public function getClient(): Collection
    {
        return $this->client;
    }

    public function addClient(ClientInformation $client): static
    {
        if (!$this->client->contains($client)) {
            $this->client->add($client);
            $client->setClient($this);
        }

        return $this;
    }

    public function removeClient(ClientInformation $client): static
    {
        if ($this->client->removeElement($client)) {
            // set the owning side to null (unless already changed)
            if ($client->getClient() === $this) {
                $client->setClient(null);
            }
        }

        return $this;
    }

    public function getConnu(): ?string
    {
        return $this->Connu;
    }

    public function setConnu(?string $Connu): static
    {
        $this->Connu = $Connu;

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

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setNote(?string $note): static
    {
        $this->note = $note;

        return $this;
    }

    public function getPrivacyPolicyAcceptedAt(): ?\DateTimeInterface
    {
        return $this->privacyPolicyAcceptedAt;
    }

    public function setPrivacyPolicyAcceptedAt(?\DateTimeInterface $privacyPolicyAcceptedAt): static
    {
        $this->privacyPolicyAcceptedAt = $privacyPolicyAcceptedAt;

        return $this;
    }
}
