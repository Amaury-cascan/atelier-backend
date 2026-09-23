<?php

namespace App\Tests\Service;

use App\Entity\BlockedSlot;
use App\Entity\ScheduleException;
use App\Entity\ScheduleVersion;
use App\Entity\WeeklyOpening;
use App\Repository\BlockedSlotRepository;
use App\Repository\ScheduleExceptionRepository;
use App\Repository\ScheduleVersionRepository;
use App\Schedule\DayKind;
use App\Schedule\TimeRange;
use App\Service\AvailabilityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AvailabilityServiceTest extends TestCase
{
    private ScheduleVersionRepository&MockObject $versions;
    private ScheduleExceptionRepository&MockObject $exceptions;
    private BlockedSlotRepository&MockObject $blocks;
    private AvailabilityService $service;

    protected function setUp(): void
    {
        $this->versions = $this->createMock(ScheduleVersionRepository::class);
        $this->exceptions = $this->createMock(ScheduleExceptionRepository::class);
        $this->blocks = $this->createMock(BlockedSlotRepository::class);
        $this->service = new AvailabilityService($this->versions, $this->exceptions, $this->blocks);
    }

    public function testResolveDayUsesVersionApplicableOnDate(): void
    {
        $this->exceptions->method('findOneByDate')->willReturn(null);
        $this->versions->method('findApplicableOn')->willReturn(
            $this->versionWithDay(1, DayKind::Open, [
                ['startMin' => 570, 'endMin' => 960],
                ['startMin' => 1110, 'endMin' => 1200],
            ], '09h30 – 16h00 · 18h30 – 20h00')
        );
        $this->blocks->method('findOverlapping')->willReturn([]);

        $resolved = $this->service->resolveDay(new \DateTimeImmutable('2026-09-21'));

        self::assertSame(DayKind::Open, $resolved->kind);
        self::assertSame('weekly', $resolved->source);
        self::assertCount(2, $resolved->bookableRanges);
    }

    public function testExceptionOverridesVersion(): void
    {
        $exception = new ScheduleException();
        $exception->setDate(new \DateTimeImmutable('2026-12-25'));
        $exception->setKind(DayKind::Closed);
        $exception->setLabel('Noël');

        $this->exceptions->method('findOneByDate')->willReturn($exception);
        $this->versions->expects($this->never())->method('findApplicableOn');
        $this->blocks->method('findOverlapping')->willReturn([]);

        $resolved = $this->service->resolveDay(new \DateTimeImmutable('2026-12-25'));

        self::assertSame(DayKind::Closed, $resolved->kind);
        self::assertSame('Noël', $resolved->label);
    }

    public function testExternalDayIsNotBookable(): void
    {
        $this->exceptions->method('findOneByDate')->willReturn(null);
        $this->versions->method('findApplicableOn')->willReturn(
            $this->versionWithDay(2, DayKind::External, [], 'Prestation extérieure')
        );
        $this->blocks->method('findOverlapping')->willReturn([]);

        $resolved = $this->service->resolveDay(new \DateTimeImmutable('2026-09-22'));

        self::assertSame(DayKind::External, $resolved->kind);
        self::assertFalse($resolved->allowsPublicBooking());
    }

    public function testBlockedSlotIsSubtractedFromOpenRanges(): void
    {
        $this->exceptions->method('findOneByDate')->willReturn(null);
        $this->versions->method('findApplicableOn')->willReturn(
            $this->versionWithDay(5, DayKind::Open, [['startMin' => 570, 'endMin' => 1200]])
        );

        $block = new BlockedSlot();
        $block->setStartAt(new \DateTimeImmutable('2026-09-25 14:00:00'));
        $block->setEndAt(new \DateTimeImmutable('2026-09-25 15:30:00'));
        $this->blocks->method('findOverlapping')->willReturn([$block]);

        $resolved = $this->service->resolveDay(new \DateTimeImmutable('2026-09-25'));

        self::assertCount(2, $resolved->bookableRanges);
        self::assertSame(840, $resolved->bookableRanges[0]->endMin);
        self::assertSame(930, $resolved->bookableRanges[1]->startMin);
    }

    public function testIsSlotBookableRejectsBlockedInterval(): void
    {
        $this->exceptions->method('findOneByDate')->willReturn(null);
        $this->versions->method('findApplicableOn')->willReturn(
            $this->versionWithDay(5, DayKind::Open, [['startMin' => 570, 'endMin' => 1200]])
        );
        $block = new BlockedSlot();
        $block->setStartAt(new \DateTimeImmutable('2026-09-25 14:00:00'));
        $block->setEndAt(new \DateTimeImmutable('2026-09-25 15:30:00'));
        $this->blocks->method('findOverlapping')->willReturn([$block]);

        self::assertFalse($this->service->isSlotBookable(new \DateTimeImmutable('2026-09-25 14:00:00'), 60));
        self::assertTrue($this->service->isSlotBookable(new \DateTimeImmutable('2026-09-25 10:00:00'), 60));
    }

    public function testIsSlotBookableRejectsClosedDay(): void
    {
        $this->exceptions->method('findOneByDate')->willReturn(null);
        $this->versions->method('findApplicableOn')->willReturn(
            $this->versionWithDay(0, DayKind::Closed, [], 'Fermé')
        );
        $this->blocks->method('findOverlapping')->willReturn([]);

        self::assertFalse($this->service->isSlotBookable(new \DateTimeImmutable('2026-09-20 10:00:00'), 60));
    }

    public function testSubtractRangesSplitsAroundHole(): void
    {
        $result = $this->service->subtractRanges(
            [new TimeRange(570, 1200)],
            [new TimeRange(840, 930)],
        );

        self::assertCount(2, $result);
        self::assertSame([570, 840], [$result[0]->startMin, $result[0]->endMin]);
        self::assertSame([930, 1200], [$result[1]->startMin, $result[1]->endMin]);
    }

    public function testGenerateSlotStartsRespectsDurationAndBlocks(): void
    {
        $this->exceptions->method('findOneByDate')->willReturn(null);
        $this->versions->method('findApplicableOn')->willReturn(
            $this->versionWithDay(5, DayKind::Open, [['startMin' => 570, 'endMin' => 660]])
        );
        $this->blocks->method('findOverlapping')->willReturn([]);

        $starts = $this->service->generateSlotStarts(new \DateTimeImmutable('2026-09-25'), 60, 30);

        self::assertSame([570, 600], $starts);
    }

    /**
     * @param list<array{startMin: int, endMin: int}> $ranges
     */
    private function versionWithDay(int $weekday, DayKind $kind, array $ranges, ?string $label = null): ScheduleVersion
    {
        $version = new ScheduleVersion();
        $version->setEffectiveFrom(new \DateTimeImmutable('2000-01-01'));
        $version->setName('Test');

        $opening = new WeeklyOpening();
        $opening->setWeekday($weekday);
        $opening->setKind($kind);
        $opening->setRanges($ranges);
        $opening->setLabel($label);
        $version->addOpening($opening);

        return $version;
    }
}
