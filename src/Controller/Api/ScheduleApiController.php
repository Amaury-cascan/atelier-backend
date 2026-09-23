<?php

namespace App\Controller\Api;

use App\Service\AvailabilityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Horaires publics : grille hebdo + exceptions à venir + créneaux bloqués à venir.
 * Consommé par Home, calendrier de réservation et (en lecture) le front admin.
 */
#[Route('/api/schedule')]
class ScheduleApiController extends AbstractController
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
    ) {
    }

    #[Route('', name: 'app_schedule_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $snapshot = $this->availabilityService->getScheduleSnapshot(
            new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'))
        );

        return $this->json([
            'success' => true,
            ...$snapshot,
        ]);
    }
}
