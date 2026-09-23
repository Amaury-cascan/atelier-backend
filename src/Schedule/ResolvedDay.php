<?php

namespace App\Schedule;

/**
 * Jour résolu : grille hebdo éventuellement remplacée par une exception,
 * puis plages effectivement réservables après soustraction des créneaux bloqués.
 */
final class ResolvedDay
{
    /**
     * @param list<TimeRange> $ranges          Plages brutes (avant blocs)
     * @param list<TimeRange> $bookableRanges  Plages après soustraction des blocs
     */
    public function __construct(
        public readonly \DateTimeImmutable $date,
        public readonly DayKind $kind,
        public readonly array $ranges,
        public readonly array $bookableRanges,
        public readonly string $label,
        public readonly string $source,
    ) {
    }

    public function allowsPublicBooking(): bool
    {
        return $this->kind->allowsPublicBooking() && $this->bookableRanges !== [];
    }

    /**
     * @return array{
     *   date: string,
     *   kind: string,
     *   label: string,
     *   ranges: list<array{startMin: int, endMin: int}>,
     *   bookableRanges: list<array{startMin: int, endMin: int}>,
     *   source: string
     * }
     */
    public function toArray(): array
    {
        return [
            'date' => $this->date->format('Y-m-d'),
            'kind' => $this->kind->value,
            'label' => $this->label,
            'ranges' => array_map(static fn (TimeRange $r) => $r->toArray(), $this->ranges),
            'bookableRanges' => array_map(static fn (TimeRange $r) => $r->toArray(), $this->bookableRanges),
            'source' => $this->source,
        ];
    }
}
