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
 * La disponibilité est vérifiée côté serveur, à l'intérieur d'une transaction
 * verrouillée sur la journée concernée : c'est la seule garantie fiable, le
 * calendrier du navigateur pouvant toujours travailler sur des données périmées.
 */
class BookAppointment
{
    private const MIN_DURATION_MINUTES = 5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AppointmentRepository $appointmentRepository,
    ) {
    }

    public function execute(Service $service, User $client, \DateTimeInterface $start): BookAppointmentResult
    {
        $startDate = \DateTimeImmutable::createFromInterface($start);
        $duration = max(self::MIN_DURATION_MINUTES, (int) $service->getDuration());
        $endDate = $startDate->modify(sprintf('+%d minutes', $duration));

        return $this->entityManager->wrapInTransaction(
            function (EntityManagerInterface $entityManager) use ($service, $client, $startDate, $endDate): BookAppointmentResult {
                $this->lockDay($entityManager, $startDate);

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

    /**
     * Sérialise les réservations d'une même journée.
     *
     * Le verrou consultatif PostgreSQL est tenu jusqu'à la fin de la transaction :
     * deux requêtes simultanées sur le même créneau ne peuvent donc pas constater
     * toutes les deux qu'il est libre. La granularité à la journée suffit ici
     * (un seul poste de travail) et garde la contention négligeable.
     */
    private function lockDay(EntityManagerInterface $entityManager, \DateTimeImmutable $day): void
    {
        $entityManager->getConnection()->executeStatement(
            'SELECT pg_advisory_xact_lock(?)',
            [(int) $day->format('Ymd')],
        );
    }
}
