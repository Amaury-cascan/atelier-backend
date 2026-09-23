<?php

namespace App\Schedule;

/**
 * Plage horaire exprimée en minutes depuis minuit (ex. 9h30 = 570).
 */
final class TimeRange
{
    public function __construct(
        public readonly int $startMin,
        public readonly int $endMin,
    ) {
        if ($this->startMin < 0 || $this->endMin > 24 * 60 || $this->endMin <= $this->startMin) {
            throw new \InvalidArgumentException(
                sprintf('Plage horaire invalide : %d–%d.', $this->startMin, $this->endMin)
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self((int) ($data['startMin'] ?? 0), (int) ($data['endMin'] ?? 0));
    }

    /**
     * @return array{startMin: int, endMin: int}
     */
    public function toArray(): array
    {
        return ['startMin' => $this->startMin, 'endMin' => $this->endMin];
    }

    public function contains(int $minute): bool
    {
        return $minute >= $this->startMin && $minute < $this->endMin;
    }

    /** La prestation [start, end[ tient entièrement dans cette plage. */
    public function containsInterval(int $startMin, int $endMin): bool
    {
        return $startMin >= $this->startMin && $endMin <= $this->endMin;
    }

    public function overlaps(int $startMin, int $endMin): bool
    {
        return $startMin < $this->endMin && $endMin > $this->startMin;
    }

    public function formatDisplay(): string
    {
        return sprintf('%s – %s', self::formatMin($this->startMin), self::formatMin($this->endMin));
    }

    public static function formatMin(int $min): string
    {
        $h = intdiv($min, 60);
        $m = $min % 60;

        return sprintf('%02dh%02d', $h, $m);
    }
}
