<?php

namespace App\Service;

use App\Entity\BlockedSlot;
use App\Entity\ScheduleException;
use App\Entity\ScheduleVersion;
use App\Entity\WeeklyOpening;
use App\Repository\BlockedSlotRepository;
use App\Repository\ScheduleExceptionRepository;
use App\Repository\ScheduleVersionRepository;
use App\Schedule\DayKind;
use App\Schedule\TimeRange;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Écritures admin sur le planning (versions, exceptions, créneaux bloqués).
 */
class ManageSchedule
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ScheduleVersionRepository $scheduleVersionRepository,
        private readonly ScheduleExceptionRepository $scheduleExceptionRepository,
        private readonly BlockedSlotRepository $blockedSlotRepository,
    ) {
    }

    /**
     * Crée une nouvelle grille applicable à partir d'une date.
     * Par défaut, copie la version active à cette date (ou la dernière connue).
     *
     * @param list<array<string, mixed>>|null $weekly
     */
    public function createVersion(\DateTimeImmutable $effectiveFrom, ?string $name, ?int $copyFromId, ?array $weekly): ScheduleVersion
    {
        $effectiveFrom = $effectiveFrom->setTime(0, 0);
        if ($this->scheduleVersionRepository->findOneBy(['effectiveFrom' => $effectiveFrom])) {
            throw new \InvalidArgumentException('Une grille existe déjà pour cette date de début.');
        }

        $source = null;
        if ($copyFromId !== null) {
            $source = $this->scheduleVersionRepository->find($copyFromId);
            if (!$source instanceof ScheduleVersion) {
                throw new \InvalidArgumentException('Grille source introuvable.');
            }
        } else {
            $source = $this->scheduleVersionRepository->findApplicableOn($effectiveFrom)
                ?? $this->scheduleVersionRepository->findOneBy([], ['effectiveFrom' => 'DESC']);
        }

        $version = new ScheduleVersion();
        $version->setEffectiveFrom($effectiveFrom);
        $version->setName($name);

        if ($weekly !== null) {
            $this->applyWeeklyPayload($version, $weekly);
        } elseif ($source instanceof ScheduleVersion) {
            foreach ($source->getOpenings() as $opening) {
                $copy = new WeeklyOpening();
                $copy->setWeekday($opening->getWeekday());
                $copy->setKind($opening->getKind());
                $copy->setRanges($opening->getRanges());
                $copy->setLabel($opening->getLabel());
                $version->addOpening($copy);
            }
        } else {
            $this->applyWeeklyPayload($version, $this->defaultClosedWeekly());
        }

        $this->ensureSevenDays($version);
        $this->entityManager->persist($version);
        $this->entityManager->flush();

        return $version;
    }

    /**
     * @param list<array<string, mixed>> $weekly
     */
    public function updateVersion(ScheduleVersion $version, ?string $name, ?\DateTimeImmutable $effectiveFrom, ?array $weekly): ScheduleVersion
    {
        if ($name !== null) {
            $version->setName($name);
        }

        if ($effectiveFrom !== null) {
            $effectiveFrom = $effectiveFrom->setTime(0, 0);
            $existing = $this->scheduleVersionRepository->findOneBy(['effectiveFrom' => $effectiveFrom]);
            if ($existing instanceof ScheduleVersion && $existing->getId() !== $version->getId()) {
                throw new \InvalidArgumentException('Une grille existe déjà pour cette date de début.');
            }
            $version->setEffectiveFrom($effectiveFrom);
        }

        if ($weekly !== null) {
            $this->applyWeeklyPayload($version, $weekly);
            $this->ensureSevenDays($version);
        }

        $this->entityManager->flush();

        return $version;
    }

    public function deleteVersion(ScheduleVersion $version): void
    {
        if ($this->scheduleVersionRepository->count([]) <= 1) {
            throw new \InvalidArgumentException('Impossible de supprimer la dernière grille.');
        }

        $this->entityManager->remove($version);
        $this->entityManager->flush();
    }

    /**
     * @param list<array{startMin: int, endMin: int}> $ranges
     */
    public function upsertException(\DateTimeImmutable $date, DayKind $kind, array $ranges, ?string $label, ?int $id = null): ScheduleException
    {
        $date = $date->setTime(0, 0);
        $exception = $id !== null
            ? $this->scheduleExceptionRepository->find($id)
            : $this->scheduleExceptionRepository->findOneByDate($date);

        if ($id !== null && !$exception instanceof ScheduleException) {
            throw new \InvalidArgumentException('Exception introuvable.');
        }

        if (!$exception instanceof ScheduleException) {
            $exception = new ScheduleException();
            $exception->setDate($date);
            $this->entityManager->persist($exception);
        } else {
            $conflict = $this->scheduleExceptionRepository->findOneByDate($date);
            if ($conflict instanceof ScheduleException && $conflict->getId() !== $exception->getId()) {
                throw new \InvalidArgumentException('Une exception existe déjà pour cette date.');
            }
            $exception->setDate($date);
        }

        $normalized = $this->normalizeRanges($kind, $ranges);
        $exception->setKind($kind);
        $exception->setRanges($normalized);
        $exception->setLabel($label);

        $this->entityManager->flush();

        return $exception;
    }

    public function deleteException(ScheduleException $exception): void
    {
        $this->entityManager->remove($exception);
        $this->entityManager->flush();
    }

    public function createBlockedSlot(\DateTimeImmutable $start, \DateTimeImmutable $end, ?string $reason): BlockedSlot
    {
        if ($end <= $start) {
            throw new \InvalidArgumentException("L'heure de fin doit être postérieure à l'heure de début.");
        }

        $slot = new BlockedSlot();
        $slot->setStartAt($start);
        $slot->setEndAt($end);
        $slot->setReason($reason);
        $this->entityManager->persist($slot);
        $this->entityManager->flush();

        return $slot;
    }

    public function updateBlockedSlot(BlockedSlot $slot, ?\DateTimeImmutable $start, ?\DateTimeImmutable $end, ?string $reason): BlockedSlot
    {
        if ($start !== null) {
            $slot->setStartAt($start);
        }
        if ($end !== null) {
            $slot->setEndAt($end);
        }
        if ($reason !== null) {
            $slot->setReason($reason === '' ? null : $reason);
        }
        if ($slot->getEndAt() <= $slot->getStartAt()) {
            throw new \InvalidArgumentException("L'heure de fin doit être postérieure à l'heure de début.");
        }

        $this->entityManager->flush();

        return $slot;
    }

    public function deleteBlockedSlot(BlockedSlot $slot): void
    {
        $this->entityManager->remove($slot);
        $this->entityManager->flush();
    }

    /**
     * @param list<array<string, mixed>> $weekly
     */
    private function applyWeeklyPayload(ScheduleVersion $version, array $weekly): void
    {
        $byWeekday = [];
        foreach ($weekly as $day) {
            $weekday = (int) ($day['weekday'] ?? -1);
            if ($weekday < 0 || $weekday > 6) {
                throw new \InvalidArgumentException('Jour de semaine invalide.');
            }
            $kind = DayKind::tryFrom((string) ($day['kind'] ?? ''));
            if (!$kind instanceof DayKind) {
                throw new \InvalidArgumentException('Statut de jour invalide (open, closed, external).');
            }
            $ranges = $this->normalizeRanges($kind, $day['ranges'] ?? []);
            $label = isset($day['label']) ? (string) $day['label'] : null;

            $opening = $version->getOpeningForWeekday($weekday);
            if (!$opening instanceof WeeklyOpening) {
                $opening = new WeeklyOpening();
                $opening->setWeekday($weekday);
                $version->addOpening($opening);
            }
            $opening->setKind($kind);
            $opening->setRanges($ranges);
            $opening->setLabel($label !== '' ? $label : null);
            $byWeekday[$weekday] = true;
        }

        // Les jours non fournis restent inchangés (update partiel) — pour create on ensureSevenDays.
    }

    private function ensureSevenDays(ScheduleVersion $version): void
    {
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            if ($version->getOpeningForWeekday($weekday) instanceof WeeklyOpening) {
                continue;
            }
            $opening = new WeeklyOpening();
            $opening->setWeekday($weekday);
            $opening->setKind(DayKind::Closed);
            $opening->setRanges([]);
            $opening->setLabel(DayKind::Closed->defaultLabel());
            $version->addOpening($opening);
        }
    }

    /**
     * @param list<mixed> $ranges
     *
     * @return list<array{startMin: int, endMin: int}>
     */
    private function normalizeRanges(DayKind $kind, array $ranges): array
    {
        if ($kind !== DayKind::Open) {
            return [];
        }

        $normalized = [];
        foreach ($ranges as $range) {
            if (!\is_array($range)) {
                throw new \InvalidArgumentException('Plage horaire invalide.');
            }
            $timeRange = TimeRange::fromArray($range);
            $normalized[] = $timeRange->toArray();
        }

        usort($normalized, static fn (array $a, array $b) => $a['startMin'] <=> $b['startMin']);

        // Interdiction de chevauchement entre plages du même jour
        for ($i = 1, $n = \count($normalized); $i < $n; $i++) {
            if ($normalized[$i]['startMin'] < $normalized[$i - 1]['endMin']) {
                throw new \InvalidArgumentException('Les plages horaires d’un même jour ne doivent pas se chevaucher.');
            }
        }

        if ($normalized === []) {
            throw new \InvalidArgumentException('Un jour ouvert doit avoir au moins une plage horaire.');
        }

        return $normalized;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function defaultClosedWeekly(): array
    {
        $weekly = [];
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $weekly[] = [
                'weekday' => $weekday,
                'kind' => DayKind::Closed->value,
                'ranges' => [],
                'label' => DayKind::Closed->defaultLabel(),
            ];
        }

        return $weekly;
    }
}
