<?php

namespace App\Tests\Service;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Service\BookAppointment;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BookAppointmentTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private AppointmentRepository&MockObject $appointmentRepository;
    private Connection&MockObject $connection;
    private BookAppointment $bookAppointment;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->appointmentRepository = $this->createMock(AppointmentRepository::class);
        $this->connection = $this->createMock(Connection::class);

        $this->entityManager->method('getConnection')->willReturn($this->connection);
        // La transaction Doctrine est simulée : le callback est exécuté directement.
        $this->entityManager->method('wrapInTransaction')->willReturnCallback(
            fn (callable $callback) => $callback($this->entityManager)
        );

        $this->bookAppointment = new BookAppointment($this->entityManager, $this->appointmentRepository);
    }

    public function testBooksFreeSlotForTheWholeServiceDuration(): void
    {
        $this->appointmentRepository->expects($this->once())
            ->method('findOverlapping')
            ->with(
                $this->callback(static fn (\DateTimeInterface $start): bool => $start->format('Y-m-d H:i') === '2026-09-21 10:30'),
                $this->callback(static fn (\DateTimeInterface $end): bool => $end->format('Y-m-d H:i') === '2026-09-21 12:00'),
            )
            ->willReturn([]);
        $this->entityManager->expects($this->once())->method('persist');

        $result = $this->bookAppointment->execute(
            $this->service(90, 45),
            new User(),
            new \DateTimeImmutable('2026-09-21 10:30:00'),
        );

        self::assertTrue($result->isBooked());
        self::assertSame('2026-09-21 10:30:00', $result->appointment->getDate()->format('Y-m-d H:i:s'));
        self::assertSame('2026-09-21 12:00:00', $result->appointment->getEndDate()->format('Y-m-d H:i:s'));
        self::assertSame(45, $result->appointment->getPrice());
    }

    public function testLocksTheDayBeforeCheckingAvailability(): void
    {
        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with($this->stringContains('pg_advisory_xact_lock'), [20260921]);
        $this->appointmentRepository->method('findOverlapping')->willReturn([]);

        $this->bookAppointment->execute(
            $this->service(60, 30),
            new User(),
            new \DateTimeImmutable('2026-09-21 10:30:00'),
        );
    }

    public function testRefusesSlotAlreadyOccupied(): void
    {
        $this->appointmentRepository->method('findOverlapping')->willReturn([new Appointment()]);
        $this->entityManager->expects($this->never())->method('persist');

        $result = $this->bookAppointment->execute(
            $this->service(60, 30),
            new User(),
            new \DateTimeImmutable('2026-09-21 10:30:00'),
        );

        self::assertFalse($result->isBooked());
        self::assertNull($result->appointment);
    }

    /**
     * Une prestation sans durée produirait une plage vide, donc un rendez-vous
     * invisible pour la détection de chevauchement.
     */
    public function testNeverCreatesAnEmptyTimeRange(): void
    {
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
