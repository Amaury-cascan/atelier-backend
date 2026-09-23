<?php

namespace App\Schedule;

/**
 * Statut d'un jour pour le salon.
 *
 * - open : créneaux réservables sur place
 * - closed : fermé (aucune réservation publique)
 * - external : prestation extérieure (visible sur le site, pas de créneaux salon)
 */
enum DayKind: string
{
    case Open = 'open';
    case Closed = 'closed';
    case External = 'external';

    public function defaultLabel(): string
    {
        return match ($this) {
            self::Open => 'Ouvert',
            self::Closed => 'Fermé',
            self::External => 'Prestation extérieure',
        };
    }

    public function allowsPublicBooking(): bool
    {
        return $this === self::Open;
    }
}
