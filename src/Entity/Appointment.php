<?php

namespace App\Entity;

use App\Repository\AppointmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AppointmentRepository::class)]
#[ORM\Index(name: 'idx_appointment_date_end_date', columns: ['date', 'end_date'])]
#[ORM\HasLifecycleCallbacks]
class Appointment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['appointmentLinked'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['appointmentLinked'])]
    private ?\DateTimeInterface $date = null;

    #[ORM\ManyToOne(inversedBy: 'appointments')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Service $service = null;

    /** Prix du RDV en euros (modifiable, conservé si le service est supprimé). */
    #[ORM\Column(nullable: true)]
    #[Groups(['appointmentLinked'])]
    private ?int $price = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $client = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['appointmentLinked'])]
    private ?\DateTimeInterface $endDate = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;

        return $this;
    }

    public function getService(): ?Service
    {
        return $this->service;
    }

    public function setService(?Service $service): static
    {
        $this->service = $service;

        return $this;
    }

    public function getPrice(): ?int
    {
        return $this->price ?? $this->service?->getPrice();
    }

    public function setPrice(?int $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getClient(): ?User
    {
        return $this->client;
    }

    public function setClient(?User $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    /**
     * Garde-fou de dernier recours, appliqué à toutes les écritures.
     *
     * Un rendez-vous dont la fin précède le début forme une plage vide : il
     * n'entre en collision avec aucun créneau et laisse donc réserver par-dessus.
     */
    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function assertTimeRangeIsConsistent(): void
    {
        if ($this->date === null || $this->endDate === null || $this->endDate <= $this->date) {
            throw new \LogicException("L'heure de fin d'un rendez-vous doit être postérieure à son heure de début.");
        }
    }
}
