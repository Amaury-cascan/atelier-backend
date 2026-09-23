<?php

namespace App\Tests\Service;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Service\AvailabilityService;
use App\Service\BookAppointment;
use App\Service\BookAppointmentResult;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BookAppointmentTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private AppointmentRepository&MockObject $appointmentRepository;
    private AvailabilityService&MockObject $availability;
    private Connection&MockObject $connection;
    private BookAppointment $bookAppointment;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->appointmentRepository = $this->createMock(AppointmentRepository::class);
        $this->availability = $this->createMock(AvailabilityService::class);
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager->method('getConnection')->willReturn($this->connection);
        $this->entityManager->method('wrapInTransaction')->willReturnCallback(
            fn (callable $callback) => $callback($this->entityManager)
        );

        $this->bookAppointment = new BookAppointment(
            $this->entityManager,
            $this->appointmentRepository,
            $this->availability,
        );
    }

    public function testBooksFreeSlotForTheWholeServiceDuration(): void
    {
        $this->availability->method('isSlotBookable')->willReturn(true);
        $this->appointmentRepository->expects($this->once())
            ->method('findOverlapping')
            ->willReturn([]);
        $this->entityManager->expects($this->once())->method('persist');

        $result = $this->bookAppointment->execute(
            $this->service(90, 45),
            new User(),
            new \DateTimeImmutable('2026-09-21 10:30:00'),
        );

        self::assertTrue($result->isBooked());
        self::assertSame(BookAppointmentResult::REASON_BOOKED, $result->reason);
        self::assertSame('2026-09-21 12:00:00', $result->appointment->getEndDate()->format('Y-m-d H:i:s'));
    }

    public function testRefusesOutsideOpeningHours(): void
    {
        $this->availability->method('isSlotBookable')->willReturn(false);
        $this->appointmentRepository->expects($this->never())->method('findOverlapping');
        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->bookAppointment->execute(
            $this->service(60, 30),
            new User(),
            new \DateTimeImmutable('2026-09-21 10:30:00'),
        );

        self::assertFalse($result->isBooked());
        self::assertSame(BookAppointmentResult::REASON_OUTSIDE_HOURS, $result->reason);
    }

    public function testRefusesSlotAlreadyOccupied(): void
    {
        $this->availability->method('isSlotBookable')->willReturn(true);
        $this->appointmentRepository->method('findOverlapping')->willReturn([new Appointment()]);
        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->bookAppointment->execute(
            $this->service(60, 30),
            new User(),
            new \DateTimeImmutable('2026-09-21 10:30:00'),
        );

        self::assertSame(BookAppointmentResult::REASON_SLOT_UNAVAILABLE, $result->reason);
    }

    public function testLocksTheDayBeforeCheckingAvailability(): void
    {
        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('pg_advisory_xact_lock'), [20260921]);
        $this->availability->method('isSlotBookable')->willReturn(true);
        $this->appointmentRepository->method('findOverlapping')->willReturn([]);

        $this->bookAppointment->execute(
            $this->service(60, 30),
            new User(),
            new \DateTimeImmutable('2026-09-21 10:30:00'),
        );
    }

    public function testNeverCreatesAnEmptyTimeRange(): void
    {
        $this->availability->method('isSlotBookable')->willReturn(true);
        $this->appointmentRepository->method('findOverlapping')->willReturn([]);

        $result = $this->bookAppointment->execute(
            $this->service(0, 0),
            new User(),
            new \DateTimeImmutable('2026-09-21 10:30:00'),
        );

        self::assertTrue($result->isBooked());
        self::assertSame('2026-09-21 10:35:00', $result->appointment->getEndDate()->format('Y-m-d H:i:s'));
    }

    private function service(int $duration, int $price): Service
    {
        $service = new Service();
        $service->setName('Semi permanent Mains');
        $service->setDescription('Test');
        $service->setDuration($duration);
        $service->setPrice($price);

        return $service;
    }
}
