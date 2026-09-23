<?php

namespace App\Entity;

use App\Repository\ScheduleVersionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Version de la grille hebdomadaire, applicable à partir d'une date.
 *
 * Ex. : grille A depuis 2000-01-01, grille B depuis 2026-10-01, grille C depuis 2026-11-01.
 * Pour une date D on prend la version dont effectiveFrom est la plus récente ≤ D.
 */
#[ORM\Entity(repositoryClass: ScheduleVersionRepository::class)]
#[ORM\Table(name: 'schedule_version')]
#[ORM\UniqueConstraint(name: 'uniq_schedule_version_effective_from', columns: ['effective_from'])]
class ScheduleVersion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'effective_from', type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $effectiveFrom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    /** @var Collection<int, WeeklyOpening> */
    #[ORM\OneToMany(targetEntity: WeeklyOpening::class, mappedBy: 'version', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['weekday' => 'ASC'])]
    private Collection $openings;

    public function __construct()
    {
        $this->openings = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEffectiveFrom(): ?\DateTimeImmutable
    {
        return $this->effectiveFrom;
    }

    public function setEffectiveFrom(\DateTimeImmutable $effectiveFrom): static
    {
        $this->effectiveFrom = $effectiveFrom->setTime(0, 0);

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name !== null && $name !== '' ? $name : null;

        return $this;
    }

    /**
     * @return Collection<int, WeeklyOpening>
     */
    public function getOpenings(): Collection
    {
        return $this->openings;
    }

    public function addOpening(WeeklyOpening $opening): static
    {
        if (!$this->openings->contains($opening)) {
            $this->openings->add($opening);
            $opening->setVersion($this);
        }

        return $this;
    }

    public function getOpeningForWeekday(int $weekday): ?WeeklyOpening
    {
        foreach ($this->openings as $opening) {
            if ($opening->getWeekday() === $weekday) {
                return $opening;
            }
        }

        return null;
    }
}
