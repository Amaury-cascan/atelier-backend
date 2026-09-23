<?php

namespace App\Entity;

use App\Repository\ScheduleExceptionRepository;
use App\Schedule\DayKind;
use App\Schedule\TimeRange;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Remplace la grille hebdomadaire pour une date civile précise
 * (fermer un jour habituellement ouvert, ouvrir un mercredi, horaires modifiés…).
 */
#[ORM\Entity(repositoryClass: ScheduleExceptionRepository::class)]
#[ORM\Table(name: 'schedule_exception')]
#[ORM\UniqueConstraint(name: 'uniq_schedule_exception_date', columns: ['date'])]
class ScheduleException
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 16, enumType: DayKind::class)]
    private DayKind $kind = DayKind::Closed;

    /**
     * @var list<array{startMin: int, endMin: int}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $ranges = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $label = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(\DateTimeImmutable $date): static
    {
        $this->date = $date->setTime(0, 0);

        return $this;
    }

    public function getKind(): DayKind
    {
        return $this->kind;
    }

    public function setKind(DayKind $kind): static
    {
        $this->kind = $kind;

        return $this;
    }

    /**
     * @return list<array{startMin: int, endMin: int}>
     */
    public function getRanges(): array
    {
        return $this->ranges;
    }

    /**
     * @param list<array{startMin: int, endMin: int}> $ranges
     */
    public function setRanges(array $ranges): static
    {
        $this->ranges = array_values($ranges);

        return $this;
    }

    /**
     * @return list<TimeRange>
     */
    public function getTimeRanges(): array
    {
        return array_map(static fn (array $r) => TimeRange::fromArray($r), $this->ranges);
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label !== null && $label !== '' ? $label : null;

        return $this;
    }
}
