<?php

namespace App\Entity;

use App\Repository\BlockedSlotRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Plage libre explicitement retirée de la réservation publique
 * (formation, urgence, pause exceptionnelle…), sans créer de rendez-vous.
 */
#[ORM\Entity(repositoryClass: BlockedSlotRepository::class)]
#[ORM\Table(name: 'blocked_slot')]
#[ORM\Index(name: 'idx_blocked_slot_start_end', columns: ['start_at', 'end_at'])]
#[ORM\HasLifecycleCallbacks]
class BlockedSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'start_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $startAt = null;

    #[ORM\Column(name: 'end_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $endAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $reason = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStartAt(): ?\DateTimeImmutable
    {
        return $this->startAt;
    }

    public function setStartAt(\DateTimeImmutable $startAt): static
    {
        $this->startAt = $startAt;

        return $this;
    }

    public function getEndAt(): ?\DateTimeImmutable
    {
        return $this->endAt;
    }

    public function setEndAt(\DateTimeImmutable $endAt): static
    {
        $this->endAt = $endAt;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason !== null && $reason !== '' ? $reason : null;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function assertTimeRangeIsConsistent(): void
    {
        if ($this->startAt === null || $this->endAt === null || $this->endAt <= $this->startAt) {
            throw new \LogicException("L'heure de fin d'un créneau bloqué doit être postérieure à son heure de début.");
        }
    }
}
