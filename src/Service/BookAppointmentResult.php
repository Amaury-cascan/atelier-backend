<?php

namespace App\Service;

use App\Entity\Appointment;

/**
 * Résultat d'une tentative de réservation : soit le rendez-vous créé,
 * soit l'information que le créneau était déjà occupé.
 */
final class BookAppointmentResult
{
    private function __construct(
        public readonly ?Appointment $appointment,
    ) {
    }

    public static function booked(Appointment $appointment): self
    {
        return new self($appointment);
    }

    public static function slotUnavailable(): self
    {
        return new self(null);
    }

    public function isBooked(): bool
    {
        return $this->appointment !== null;
    }
}
