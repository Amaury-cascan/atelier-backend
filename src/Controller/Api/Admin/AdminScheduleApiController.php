<?php

namespace App\Controller\Api\Admin;

use App\Entity\BlockedSlot;
use App\Entity\ScheduleException;
use App\Entity\ScheduleVersion;
use App\Schedule\DayKind;
use App\Service\AvailabilityService;
use App\Service\ManageSchedule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/schedule')]
class AdminScheduleApiController extends AbstractController
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly ManageSchedule $manageSchedule,
    ) {
    }

    #[Route('', name: 'api_admin_schedule_get', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $from = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')))->modify('-30 days');

        return $this->json([
            'success' => true,
            ...$this->availabilityService->getScheduleSnapshot($from),
        ]);
    }

    #[Route('/versions', name: 'api_admin_schedule_versions_create', methods: ['POST'])]
    public function createVersion(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            if (empty($data['effectiveFrom'])) {
                return $this->json(['success' => false, 'message' => 'Date de début obligatoire.'], Response::HTTP_BAD_REQUEST);
            }
            $version = $this->manageSchedule->createVersion(
                new \DateTimeImmutable((string) $data['effectiveFrom']),
                isset($data['name']) ? (string) $data['name'] : null,
                isset($data['copyFromVersionId']) ? (int) $data['copyFromVersionId'] : null,
                isset($data['weekly']) && \is_array($data['weekly']) ? $data['weekly'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'version' => $this->availabilityService->serializeVersion($version),
        ], Response::HTTP_CREATED);
    }

    #[Route('/versions/{id}', name: 'api_admin_schedule_versions_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function updateVersion(ScheduleVersion $version, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            $updated = $this->manageSchedule->updateVersion(
                $version,
                \array_key_exists('name', $data) ? (string) ($data['name'] ?? '') : null,
                !empty($data['effectiveFrom']) ? new \DateTimeImmutable((string) $data['effectiveFrom']) : null,
                isset($data['weekly']) && \is_array($data['weekly']) ? $data['weekly'] : null,
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'version' => $this->availabilityService->serializeVersion($updated),
        ]);
    }

    #[Route('/versions/{id}', name: 'api_admin_schedule_versions_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteVersion(ScheduleVersion $version): JsonResponse
    {
        try {
            $this->manageSchedule->deleteVersion($version);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json(['success' => true]);
    }

    #[Route('/exceptions', name: 'api_admin_schedule_exceptions_create', methods: ['POST'])]
    public function createException(Request $request): JsonResponse
    {
        return $this->upsertException($request);
    }

    #[Route('/exceptions/{id}', name: 'api_admin_schedule_exceptions_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function updateException(ScheduleException $exception, Request $request): JsonResponse
    {
        return $this->upsertException($request, $exception->getId());
    }

    #[Route('/exceptions/{id}', name: 'api_admin_schedule_exceptions_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteException(ScheduleException $exception): JsonResponse
    {
        $this->manageSchedule->deleteException($exception);

        return $this->json(['success' => true]);
    }

    #[Route('/blocked-slots', name: 'api_admin_schedule_blocks_create', methods: ['POST'])]
    public function createBlocked(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            if (empty($data['start']) || empty($data['end'])) {
                return $this->json(['success' => false, 'message' => 'Début et fin obligatoires.'], Response::HTTP_BAD_REQUEST);
            }
            $slot = $this->manageSchedule->createBlockedSlot(
                new \DateTimeImmutable((string) $data['start']),
                new \DateTimeImmutable((string) $data['end']),
                isset($data['reason']) ? (string) $data['reason'] : null,
            );
        } catch (\InvalidArgumentException|\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'blockedSlot' => $this->availabilityService->serializeBlocked($slot),
        ], Response::HTTP_CREATED);
    }

    #[Route('/blocked-slots/{id}', name: 'api_admin_schedule_blocks_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function updateBlocked(BlockedSlot $slot, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            $updated = $this->manageSchedule->updateBlockedSlot(
                $slot,
                !empty($data['start']) ? new \DateTimeImmutable((string) $data['start']) : null,
                !empty($data['end']) ? new \DateTimeImmutable((string) $data['end']) : null,
                \array_key_exists('reason', $data) ? (string) ($data['reason'] ?? '') : null,
            );
        } catch (\InvalidArgumentException|\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'blockedSlot' => $this->availabilityService->serializeBlocked($updated),
        ]);
    }

    #[Route('/blocked-slots/{id}', name: 'api_admin_schedule_blocks_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function deleteBlocked(BlockedSlot $slot): JsonResponse
    {
        $this->manageSchedule->deleteBlockedSlot($slot);

        return $this->json(['success' => true]);
    }

    private function upsertException(Request $request, ?int $id = null): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        try {
            if (empty($data['date']) || empty($data['kind'])) {
                return $this->json(['success' => false, 'message' => 'Date et statut obligatoires.'], Response::HTTP_BAD_REQUEST);
            }
            $kind = DayKind::tryFrom((string) $data['kind']);
            if (!$kind instanceof DayKind) {
                return $this->json(['success' => false, 'message' => 'Statut invalide.'], Response::HTTP_BAD_REQUEST);
            }
            $exception = $this->manageSchedule->upsertException(
                new \DateTimeImmutable((string) $data['date']),
                $kind,
                \is_array($data['ranges'] ?? null) ? $data['ranges'] : [],
                isset($data['label']) ? (string) $data['label'] : null,
                $id,
            );
        } catch (\InvalidArgumentException|\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'exception' => $this->availabilityService->serializeException($exception),
        ], $id === null ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
