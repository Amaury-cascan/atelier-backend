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
use App\Schedule\ResolvedDay;
use App\Schedule\TimeRange;

/**
 * Source unique de vérité pour les horaires d'ouverture.
 *
 * Ordre de résolution pour une date D :
 * 1. exception de jour si elle existe ;
 * 2. sinon règle hebdomadaire de la version applicable (effectiveFrom ≤ D la plus récente) ;
 * 3. soustraction des créneaux bloqués qui chevauchent D.
 */
class AvailabilityService
{
    private const DEFAULT_TZ = 'Europe/Paris';

    public function __construct(
        private readonly ScheduleVersionRepository $scheduleVersionRepository,
        private readonly ScheduleExceptionRepository $scheduleExceptionRepository,
        private readonly BlockedSlotRepository $blockedSlotRepository,
    ) {
    }

    /**
     * @return array{
     *   versions: list<array<string, mixed>>,
     *   weekly: list<array<string, mixed>>,
     *   exceptions: list<array<string, mixed>>,
     *   blockedSlots: list<array<string, mixed>>
     * }
     */
    public function getScheduleSnapshot(?\DateTimeInterface $from = null): array
    {
        $tz = new \DateTimeZone(self::DEFAULT_TZ);
        $fromDay = $from !== null
            ? \DateTimeImmutable::createFromInterface($from)->setTimezone($tz)->setTime(0, 0)
            : new \DateTimeImmutable('today', $tz);
        $today = new \DateTimeImmutable('today', $tz);

        $versions = array_map(
            fn (ScheduleVersion $version) => $this->serializeVersion($version),
            $this->scheduleVersionRepository->findAllOrdered(),
        );

        $active = $this->scheduleVersionRepository->findApplicableOn($today);
        $weekly = $active instanceof ScheduleVersion
            ? $this->serializeVersion($active)['weekly']
            : $this->emptyWeeklyPayload();

        $exceptions = array_map(
            fn (ScheduleException $exception) => $this->serializeException($exception),
            $this->scheduleExceptionRepository->findFrom($fromDay),
        );

        $blockedSlots = array_map(
            fn (BlockedSlot $slot) => $this->serializeBlocked($slot),
            $this->blockedSlotRepository->findFrom($fromDay),
        );

        return [
            'versions' => $versions,
            'weekly' => $weekly,
            'exceptions' => $exceptions,
            'blockedSlots' => $blockedSlots,
        ];
    }

    public function resolveDay(\DateTimeInterface $date): ResolvedDay
    {
        $tz = new \DateTimeZone(self::DEFAULT_TZ);
        $day = \DateTimeImmutable::createFromInterface($date)->setTimezone($tz)->setTime(0, 0);

        $exception = $this->scheduleExceptionRepository->findOneByDate($day);
        if ($exception instanceof ScheduleException) {
            $kind = $exception->getKind();
            $ranges = $kind === DayKind::Open ? $exception->getTimeRanges() : [];
            $label = $this->resolveLabel($exception->getLabel(), $kind, $ranges);
            $source = 'exception';
        } else {
            $version = $this->scheduleVersionRepository->findApplicableOn($day);
            $opening = $version?->getOpeningForWeekday((int) $day->format('w'));
            if ($opening instanceof WeeklyOpening) {
                $kind = $opening->getKind();
                $ranges = $kind === DayKind::Open ? $opening->getTimeRanges() : [];
                $label = $this->resolveLabel($opening->getLabel(), $kind, $ranges);
            } else {
                $kind = DayKind::Closed;
                $ranges = [];
                $label = DayKind::Closed->defaultLabel();
            }
            $source = 'weekly';
        }

        $bookableRanges = $kind === DayKind::Open
            ? $this->subtractBlockedRanges($day, $ranges)
            : [];

        return new ResolvedDay($day, $kind, $ranges, $bookableRanges, $label, $source);
    }

    public function isSlotBookable(\DateTimeInterface $start, int $durationMinutes): bool
    {
        if ($durationMinutes < 1) {
            return false;
        }

        $startAt = $this->asParisWallClock(\DateTimeImmutable::createFromInterface($start));
        if ($startAt === null) {
            return false;
        }
        $endAt = $startAt->modify(sprintf('+%d minutes', $durationMinutes));

        if ($startAt->format('Y-m-d') !== $endAt->format('Y-m-d')) {
            return false;
        }

        $resolved = $this->resolveDay($startAt);
        if (!$resolved->allowsPublicBooking()) {
            return false;
        }

        $startMin = ((int) $startAt->format('H')) * 60 + (int) $startAt->format('i');
        $endMin = $startMin + $durationMinutes;

        foreach ($resolved->bookableRanges as $range) {
            if ($range->containsInterval($startMin, $endMin)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<int>
     */
    public function generateSlotStarts(\DateTimeInterface $date, int $durationMinutes, int $stepMinutes = 30): array
    {
        $resolved = $this->resolveDay($date);
        if (!$resolved->allowsPublicBooking() || $durationMinutes < 1 || $stepMinutes < 1) {
            return [];
        }

        $starts = [];
        foreach ($resolved->bookableRanges as $range) {
            for ($t = $range->startMin; $t + $durationMinutes <= $range->endMin; $t += $stepMinutes) {
                $starts[] = $t;
            }
        }

        return $starts;
    }

    /**
     * @param list<TimeRange> $ranges
     *
     * @return list<TimeRange>
     */
    private function subtractBlockedRanges(\DateTimeImmutable $day, array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }

        $tz = new \DateTimeZone(self::DEFAULT_TZ);
        $dayStart = $day->setTimezone($tz)->setTime(0, 0);
        $dayEnd = $dayStart->modify('+1 day');
        $blocks = $this->blockedSlotRepository->findOverlapping($dayStart, $dayEnd);

        $blockRanges = [];
        foreach ($blocks as $block) {
            $startAt = $this->asParisWallClock($block->getStartAt());
            $endAt = $this->asParisWallClock($block->getEndAt());
            if ($startAt === null || $endAt === null) {
                continue;
            }

            $startMin = $startAt <= $dayStart
                ? 0
                : ((int) $startAt->format('H')) * 60 + (int) $startAt->format('i');
            $endMin = $endAt >= $dayEnd
                ? 24 * 60
                : ((int) $endAt->format('H')) * 60 + (int) $endAt->format('i');

            if ($endMin > $startMin) {
                $blockRanges[] = new TimeRange($startMin, $endMin);
            }
        }

        return $this->subtractRanges($ranges, $blockRanges);
    }

    private function asParisWallClock(?\DateTimeImmutable $value): ?\DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        return new \DateTimeImmutable($value->format('Y-m-d H:i:s'), new \DateTimeZone(self::DEFAULT_TZ));
    }

    /**
     * @param list<TimeRange> $sources
     * @param list<TimeRange> $holes
     *
     * @return list<TimeRange>
     */
    public function subtractRanges(array $sources, array $holes): array
    {
        $result = $sources;
        foreach ($holes as $hole) {
            $next = [];
            foreach ($result as $range) {
                if (!$range->overlaps($hole->startMin, $hole->endMin)) {
                    $next[] = $range;
                    continue;
                }
                if ($hole->startMin > $range->startMin) {
                    $next[] = new TimeRange($range->startMin, min($hole->startMin, $range->endMin));
                }
                if ($hole->endMin < $range->endMin) {
                    $next[] = new TimeRange(max($hole->endMin, $range->startMin), $range->endMin);
                }
            }
            $result = $next;
        }

        return array_values(array_filter(
            $result,
            static fn (TimeRange $r) => $r->endMin > $r->startMin
        ));
    }

    /**
     * @param list<TimeRange> $ranges
     */
    public function resolveLabel(?string $label, DayKind $kind, array $ranges): string
    {
        if ($label !== null && $label !== '') {
            return $label;
        }
        if ($kind === DayKind::Open && $ranges !== []) {
            return implode(' · ', array_map(static fn (TimeRange $r) => $r->formatDisplay(), $ranges));
        }

        return $kind->defaultLabel();
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeVersion(ScheduleVersion $version): array
    {
        $weekly = $this->emptyWeeklyPayload();
        foreach ($version->getOpenings() as $opening) {
            $weekly[$opening->getWeekday()] = $this->serializeWeekly($opening);
        }

        return [
            'id' => $version->getId(),
            'effectiveFrom' => $version->getEffectiveFrom()?->format('Y-m-d'),
            'name' => $version->getName(),
            'weekly' => array_values($weekly),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeWeekly(WeeklyOpening $opening): array
    {
        $ranges = $opening->getKind() === DayKind::Open ? $opening->getTimeRanges() : [];

        return [
            'weekday' => $opening->getWeekday(),
            'kind' => $opening->getKind()->value,
            'ranges' => array_map(static fn (TimeRange $r) => $r->toArray(), $ranges),
            'label' => $this->resolveLabel($opening->getLabel(), $opening->getKind(), $ranges),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function emptyWeeklyPayload(): array
    {
        $weekly = [];
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $weekly[$weekday] = [
                'weekday' => $weekday,
                'kind' => DayKind::Closed->value,
                'ranges' => [],
                'label' => DayKind::Closed->defaultLabel(),
            ];
        }

        return $weekly;
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeException(ScheduleException $exception): array
    {
        $ranges = $exception->getKind() === DayKind::Open ? $exception->getTimeRanges() : [];

        return [
            'id' => $exception->getId(),
            'date' => $exception->getDate()?->format('Y-m-d'),
            'kind' => $exception->getKind()->value,
            'ranges' => array_map(static fn (TimeRange $r) => $r->toArray(), $ranges),
            'label' => $this->resolveLabel($exception->getLabel(), $exception->getKind(), $ranges),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeBlocked(BlockedSlot $slot): array
    {
        return [
            'id' => $slot->getId(),
            'start' => $slot->getStartAt()?->format('Y-m-d\TH:i:s'),
            'end' => $slot->getEndAt()?->format('Y-m-d\TH:i:s'),
            'reason' => $slot->getReason(),
        ];
    }
}
