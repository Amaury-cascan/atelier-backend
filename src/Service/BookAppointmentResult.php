<?php

namespace App\Service;

use App\Entity\Appointment;

/**
 * Résultat d'une tentative de réservation publique.
 */
final class BookAppointmentResult
{
    public const REASON_BOOKED = 'booked';
    public const REASON_SLOT_UNAVAILABLE = 'slot_unavailable';
    public const REASON_OUTSIDE_HOURS = 'outside_hours';

    private function __construct(
        public readonly ?Appointment $appointment,
        public readonly string $reason,
    ) {
    }

    public static function booked(Appointment $appointment): self
    {
        return new self($appointment, self::REASON_BOOKED);
    }

    public static function slotUnavailable(): self
    {
        return new self(null, self::REASON_SLOT_UNAVAILABLE);
    }

    public static function outsideHours(): self
    {
        return new self(null, self::REASON_OUTSIDE_HOURS);
    }

    public function isBooked(): bool
    {
        return $this->appointment !== null;
    }
}
