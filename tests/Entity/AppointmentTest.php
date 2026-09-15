<?php

namespace App\Tests\Entity;

use App\Entity\Appointment;
use PHPUnit\Framework\TestCase;

class AppointmentTest extends TestCase
{
    public function testAcceptsRangeEndingAfterItsStart(): void
    {
        $appointment = $this->appointment('2026-09-21 10:30', '2026-09-21 12:00');

        $appointment->assertTimeRangeIsConsistent();

        self::assertSame('12:00', $appointment->getEndDate()->format('H:i'));
    }

    public function testRejectsEndBeforeStart(): void
    {
        $appointment = $this->appointment('2026-09-21 10:30', '2026-09-21 09:30');

        $this->expectException(\LogicException::class);

        $appointment->assertTimeRangeIsConsistent();
    }

    public function testRejectsEmptyRange(): void
    {
        $appointment = $this->appointment('2026-09-21 10:30', '2026-09-21 10:30');

        $this->expectException(\LogicException::class);

        $appointment->assertTimeRangeIsConsistent();
    }

    public function testRejectsMissingEndDate(): void
    {
        $appointment = new Appointment();
        $appointment->setDate(new \DateTime('2026-09-21 10:30'));

        $this->expectException(\LogicException::class);

        $appointment->assertTimeRangeIsConsistent();
    }

    private function appointment(string $start, string $end): Appointment
    {
        $appointment = new Appointment();
        $appointment->setDate(new \DateTime($start));
        $appointment->setEndDate(new \DateTime($end));

        return $appointment;
    }
}
