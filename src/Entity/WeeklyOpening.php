<?php

namespace App\Entity;

use App\Repository\WeeklyOpeningRepository;
use App\Schedule\DayKind;
use App\Schedule\TimeRange;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Règle d'un jour de la semaine au sein d'une version de grille
 * (0 = dimanche … 6 = samedi, comme JavaScript).
 */
#[ORM\Entity(repositoryClass: WeeklyOpeningRepository::class)]
#[ORM\Table(name: 'weekly_opening')]
#[ORM\UniqueConstraint(name: 'uniq_weekly_opening_version_weekday', columns: ['version_id', 'weekday'])]
class WeeklyOpening
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'openings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ScheduleVersion $version = null;

    /** 0 = dimanche, 1 = lundi, …, 6 = samedi */
    #[ORM\Column(type: Types::SMALLINT)]
    private int $weekday = 0;

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

    public function getVersion(): ?ScheduleVersion
    {
        return $this->version;
    }

    public function setVersion(?ScheduleVersion $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getWeekday(): int
    {
        return $this->weekday;
    }

    public function setWeekday(int $weekday): static
    {
        if ($weekday < 0 || $weekday > 6) {
            throw new \InvalidArgumentException('Le jour de semaine doit être entre 0 (dimanche) et 6 (samedi).');
        }
        $this->weekday = $weekday;

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
