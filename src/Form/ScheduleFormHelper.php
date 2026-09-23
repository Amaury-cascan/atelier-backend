<?php

namespace App\Form;

use App\Schedule\DayKind;

/**
 * Conversion formulaire Twig ↔ payload ManageSchedule (minutes depuis minuit).
 */
final class ScheduleFormHelper
{
    private const DAY_LABELS = [
        0 => 'Dimanche',
        1 => 'Lundi',
        2 => 'Mardi',
        3 => 'Mercredi',
        4 => 'Jeudi',
        5 => 'Vendredi',
        6 => 'Samedi',
    ];

    public static function dayLabels(): array
    {
        return self::DAY_LABELS;
    }

    public static function kindChoices(): array
    {
        return [
            'Ouvert' => DayKind::Open->value,
            'Fermé' => DayKind::Closed->value,
            'Extérieur' => DayKind::External->value,
        ];
    }

    public static function minutesFromTime(?\DateTimeInterface $time): ?int
    {
        if ($time === null) {
            return null;
        }

        return ((int) $time->format('H')) * 60 + (int) $time->format('i');
    }

    public static function timeFromMinutes(int $min): \DateTimeImmutable
    {
        $h = intdiv($min, 60);
        $m = $min % 60;

        return new \DateTimeImmutable(sprintf('1970-01-01 %02d:%02d:00', $h, $m));
    }

    /**
     * Prépare les données du formulaire version à partir d'un weekly sérialisé API.
     *
     * @param list<array<string, mixed>> $weekly
     *
     * @return list<array<string, mixed>>
     */
    public static function weeklyForForm(array $weekly): array
    {
        $byDay = [];
        foreach ($weekly as $day) {
            $byDay[(int) ($day['weekday'] ?? -1)] = $day;
        }

        $result = [];
        for ($weekday = 0; $weekday <= 6; $weekday++) {
            $src = $byDay[$weekday] ?? [
                'weekday' => $weekday,
                'kind' => DayKind::Closed->value,
                'label' => DayKind::Closed->defaultLabel(),
                'ranges' => [],
            ];
            $kind = (string) ($src['kind'] ?? DayKind::Closed->value);
            $ranges = [];
            foreach ($src['ranges'] ?? [] as $range) {
                $startMin = (int) ($range['startMin'] ?? 0);
                $endMin = (int) ($range['endMin'] ?? 0);
                $ranges[] = [
                    'start' => self::timeFromMinutes($startMin),
                    'end' => self::timeFromMinutes($endMin),
                ];
            }
            if ($kind === DayKind::Open->value && $ranges === []) {
                $ranges[] = [
                    'start' => self::timeFromMinutes(9 * 60 + 30),
                    'end' => self::timeFromMinutes(20 * 60),
                ];
            }

            $result[] = [
                'weekday' => $weekday,
                'kind' => $kind,
                'label' => (string) ($src['label'] ?? ''),
                'ranges' => $ranges,
            ];
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $weekly
     *
     * @return list<array<string, mixed>>
     */
    public static function weeklyToPayload(array $weekly): array
    {
        $payload = [];
        foreach ($weekly as $day) {
            $kind = (string) ($day['kind'] ?? DayKind::Closed->value);
            $ranges = [];
            if ($kind === DayKind::Open->value) {
                foreach ($day['ranges'] ?? [] as $range) {
                    $start = self::minutesFromTime($range['start'] ?? null);
                    $end = self::minutesFromTime($range['end'] ?? null);
                    if ($start === null || $end === null) {
                        continue;
                    }
                    $ranges[] = ['startMin' => $start, 'endMin' => $end];
                }
            }
            $payload[] = [
                'weekday' => (int) ($day['weekday'] ?? 0),
                'kind' => $kind,
                'label' => isset($day['label']) ? (string) $day['label'] : null,
                'ranges' => $ranges,
            ];
        }

        return $payload;
    }

    /**
     * @param list<array{startMin: int, endMin: int}> $ranges
     *
     * @return list<array{start: \DateTimeImmutable, end: \DateTimeImmutable}>
     */
    public static function rangesForForm(array $ranges): array
    {
        if ($ranges === []) {
            return [[
                'start' => self::timeFromMinutes(9 * 60 + 30),
                'end' => self::timeFromMinutes(20 * 60),
            ]];
        }

        $result = [];
        foreach ($ranges as $range) {
            $result[] = [
                'start' => self::timeFromMinutes((int) $range['startMin']),
                'end' => self::timeFromMinutes((int) $range['endMin']),
            ];
        }

        return $result;
    }

    /**
     * @param list<array<string, mixed>> $ranges
     *
     * @return list<array{startMin: int, endMin: int}>
     */
    public static function rangesToPayload(array $ranges): array
    {
        $payload = [];
        foreach ($ranges as $range) {
            $start = self::minutesFromTime($range['start'] ?? null);
            $end = self::minutesFromTime($range['end'] ?? null);
            if ($start === null || $end === null) {
                continue;
            }
            $payload[] = ['startMin' => $start, 'endMin' => $end];
        }

        return $payload;
    }

    public static function blankWeeklyForForm(): array
    {
        return self::weeklyForForm([]);
    }
}
