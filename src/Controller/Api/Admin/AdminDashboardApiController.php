<?php

namespace App\Controller\Api\Admin;

use App\Repository\AppointmentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin')]
class AdminDashboardApiController extends AbstractController
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
    ) {
    }

    #[Route('/dashboard', name: 'api_admin_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $tz = new \DateTimeZone('Europe/Paris');
        $now = new \DateTimeImmutable('now', $tz);

        $keys = [
            'day' => $now->format('Y-m-d'),
            'week' => $now->format('Y') . '-' . $now->format('W'),
            'month' => $now->format('Y-m'),
            'year' => $now->format('Y'),
        ];

        $stats = [
            'day' => ['count' => 0, 'total' => 0],
            'week' => ['count' => 0, 'total' => 0],
            'month' => ['count' => 0, 'total' => 0],
            'year' => ['count' => 0, 'total' => 0],
        ];

        foreach ($this->appointmentRepository->findAll() as $appointment) {
            $price = (int) ($appointment->getPrice() ?? 0);
            if ($price <= 0 || !$appointment->getDate()) {
                continue;
            }

            $date = \DateTimeImmutable::createFromInterface($appointment->getDate())->setTimezone($tz);
            $buckets = [
                'day' => $date->format('Y-m-d'),
                'week' => $date->format('Y') . '-' . $date->format('W'),
                'month' => $date->format('Y-m'),
                'year' => $date->format('Y'),
            ];

            foreach ($buckets as $period => $value) {
                if ($value === $keys[$period]) {
                    $stats[$period]['count']++;
                    $stats[$period]['total'] += $price;
                }
            }
        }

        return $this->json(['success' => true, 'stats' => $stats, 'generatedAt' => $now->format(\DateTimeInterface::ATOM)]);
    }

    #[Route('/statistics', name: 'api_admin_statistics', methods: ['GET'])]
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->query->get('period', 'monthly');
        $tz = new \DateTimeZone('Europe/Paris');
        $now = new \DateTimeImmutable('now', $tz);

        $selected = match ($period) {
            'daily' => $request->query->get('date', $now->format('Y-m-d')),
            'weekly' => $request->query->get('week', $now->format('W') . '-' . $now->format('Y')),
            'yearly' => $request->query->get('year', $now->format('Y')),
            default => $request->query->get('month', $now->format('m') . '-' . $now->format('Y')),
        };

        $byService = [];
        $total = ['count' => 0, 'total' => 0];

        foreach ($this->appointmentRepository->findAll() as $appointment) {
            if (!$appointment->getDate()) {
                continue;
            }
            $date = \DateTimeImmutable::createFromInterface($appointment->getDate())->setTimezone($tz);
            $key = match ($period) {
                'daily' => $date->format('Y-m-d'),
                'weekly' => $date->format('W') . '-' . $date->format('Y'),
                'yearly' => $date->format('Y'),
                default => $date->format('m') . '-' . $date->format('Y'),
            };

            if ($key !== $selected) {
                continue;
            }

            $serviceName = $appointment->getService()?->getName() ?? 'Sans service';
            if (!isset($byService[$serviceName])) {
                $byService[$serviceName] = ['service' => $serviceName, 'count' => 0, 'total' => 0];
            }

            $price = (int) ($appointment->getPrice() ?? 0);
            $byService[$serviceName]['count']++;
            $byService[$serviceName]['total'] += $price;
            $total['count']++;
            $total['total'] += $price;
        }

        $rows = array_values($byService);
        usort($rows, static fn (array $a, array $b) => $b['total'] <=> $a['total']);

        foreach ($rows as &$row) {
            $row['share'] = $total['total'] > 0 ? round(($row['total'] / $total['total']) * 100, 1) : 0;
        }
        unset($row);

        return $this->json([
            'success' => true,
            'period' => $period,
            'selected' => $selected,
            'total' => $total,
            'rows' => $rows,
        ]);
    }
}
