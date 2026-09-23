<?php

namespace App\Service;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Réservation d'un créneau par une cliente.
 *
 * Vérifie horaires d'ouverture + créneaux bloqués, puis chevauchement RDV,
 * dans une transaction verrouillée sur la journée.
 */
class BookAppointment
{
    private const MIN_DURATION_MINUTES = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AppointmentRepository $appointmentRepository,
        private readonly AvailabilityService $availabilityService,
    ) {
    }

    public function execute(Service $service, User $client, \DateTimeInterface $start): BookAppointmentResult
    {
        $startDate = \DateTimeImmutable::createFromInterface($start);
        $duration = max(self::MIN_DURATION_MINUTES, (int) $service->getDuration());
        $endDate = $startDate->modify(sprintf('+%d minutes', $duration));

        return $this->entityManager->wrapInTransaction(
            function (EntityManagerInterface $entityManager) use ($service, $client, $startDate, $endDate, $duration): BookAppointmentResult {
                $this->lockDay($entityManager, $startDate);

                if (!$this->availabilityService->isSlotBookable($startDate, $duration)) {
                    return BookAppointmentResult::outsideHours();
                }

                if ($this->appointmentRepository->findOverlapping($startDate, $endDate) !== []) {
                    return BookAppointmentResult::slotUnavailable();
                }

                $appointment = new Appointment();
                $appointment->setDate(\DateTime::createFromImmutable($startDate));
                $appointment->setEndDate(\DateTime::createFromImmutable($endDate));
                $appointment->setService($service);
                $appointment->setClient($client);
                $appointment->setPrice($service->getPrice());

                $entityManager->persist($appointment);

                return BookAppointmentResult::booked($appointment);
            }
        );
    }

    private function lockDay(EntityManagerInterface $entityManager, \DateTimeImmutable $day): void
    {
        $entityManager->getConnection()->executeStatement(
            'SELECT pg_advisory_xact_lock(?)',
            [(int) $day->format('Ymd')],
        );
    }
}
