<?php

namespace App\Tests\Schedule;

use App\Schedule\TimeRange;
use PHPUnit\Framework\TestCase;

class TimeRangeTest extends TestCase
{
    public function testRejectsEmptyOrInvertedRange(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TimeRange(600, 600);
    }

    public function testContainsIntervalStrictlyInside(): void
    {
        $range = new TimeRange(570, 960); // 9h30–16h

        self::assertTrue($range->containsInterval(570, 660));
        self::assertTrue($range->containsInterval(600, 960));
        self::assertFalse($range->containsInterval(570, 961));
        self::assertFalse($range->containsInterval(560, 600));
    }

    public function testFormatDisplay(): void
    {
        self::assertSame('09h30 – 16h00', (new TimeRange(570, 960))->formatDisplay());
    }
}
