<?php

namespace App\Controller;

use App\Entity\BlockedSlot;
use App\Entity\ScheduleException;
use App\Entity\ScheduleVersion;
use App\Form\BlockedSlotType;
use App\Form\ScheduleExceptionType;
use App\Form\ScheduleFormHelper;
use App\Form\ScheduleVersionType;
use App\Schedule\DayKind;
use App\Service\AvailabilityService;
use App\Service\ManageSchedule;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/administration/horaires')]
#[IsGranted('ROLE_ADMIN')]
class ScheduleController extends AbstractController
{
    public function __construct(
        private readonly AvailabilityService $availabilityService,
        private readonly ManageSchedule $manageSchedule,
    ) {
    }

    #[Route('/', name: 'app_schedule_index', methods: ['GET'])]
    public function index(): Response
    {
        $from = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')))->modify('-30 days');
        $snapshot = $this->availabilityService->getScheduleSnapshot($from);
        $today = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')))->format('Y-m-d');

        // versions ASC by effectiveFrom — dernière ≤ aujourd’hui = active
        $activeVersionId = null;
        foreach ($snapshot['versions'] as $version) {
            if (($version['effectiveFrom'] ?? '') <= $today) {
                $activeVersionId = $version['id'];
            }
        }

        return $this->render('schedule/index.html.twig', [
            'versions' => $snapshot['versions'],
            'exceptions' => $snapshot['exceptions'],
            'blockedSlots' => $snapshot['blockedSlots'],
            'activeVersionId' => $activeVersionId,
            'dayLabels' => ScheduleFormHelper::dayLabels(),
        ]);
    }

    #[Route('/grilles/nouvelle', name: 'app_schedule_version_new', methods: ['GET', 'POST'])]
    public function newVersion(Request $request): Response
    {
        $from = (new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')))->modify('-30 days');
        $snapshot = $this->availabilityService->getScheduleSnapshot($from);
        $today = new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'));

        $baseWeekly = $snapshot['weekly'] ?? [];
        $copyFromId = null;
        foreach ($snapshot['versions'] as $version) {
            if (($version['effectiveFrom'] ?? '') <= $today->format('Y-m-d')) {
                $copyFromId = $version['id'];
                $baseWeekly = $version['weekly'] ?? $baseWeekly;
            }
        }

        $form = $this->createForm(ScheduleVersionType::class, [
            'effectiveFrom' => $today,
            'name' => '',
            'weekly' => ScheduleFormHelper::weeklyForForm($baseWeekly),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $this->manageSchedule->createVersion(
                    $data['effectiveFrom'],
                    $data['name'] !== '' ? $data['name'] : null,
                    $copyFromId !== null ? (int) $copyFromId : null,
                    ScheduleFormHelper::weeklyToPayload($data['weekly'] ?? []),
                );
                $this->addFlash('success', 'Grille créée.');

                return $this->redirectToRoute('app_schedule_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('schedule/version_form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle grille',
            'subtitle' => 'Copie de la grille active à cette date — ajuste ensuite.',
            'dayLabels' => ScheduleFormHelper::dayLabels(),
            'isEdit' => false,
        ]);
    }

    #[Route('/grilles/{id}/editer', name: 'app_schedule_version_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editVersion(Request $request, ScheduleVersion $version): Response
    {
        $serialized = $this->availabilityService->serializeVersion($version);
        $form = $this->createForm(ScheduleVersionType::class, [
            'effectiveFrom' => $version->getEffectiveFrom(),
            'name' => $version->getName() ?? '',
            'weekly' => ScheduleFormHelper::weeklyForForm($serialized['weekly'] ?? []),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                $this->manageSchedule->updateVersion(
                    $version,
                    (string) ($data['name'] ?? ''),
                    $data['effectiveFrom'] ?? null,
                    ScheduleFormHelper::weeklyToPayload($data['weekly'] ?? []),
                );
                $this->addFlash('success', 'Grille enregistrée.');

                return $this->redirectToRoute('app_schedule_index');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->render('schedule/version_form.html.twig', [
            'form' => $form,
            'title' => 'Modifier la grille',
            'subtitle' => $version->getName() ?: $version->getEffectiveFrom()?->format('d/m/Y'),
            'dayLabels' => ScheduleFormHelper::dayLabels(),
            'isEdit' => true,
            'version' => $version,
        ]);
    }

    #[Route('/grilles/{id}', name: 'app_schedule_version_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteVersion(Request $request, ScheduleVersion $version): Response
    {
        if ($this->isCsrfTokenValid('delete_version'.$version->getId(), (string) $request->request->get('_token'))) {
            try {
                $this->manageSchedule->deleteVersion($version);
                $this->addFlash('success', 'Grille supprimée.');
            } catch (\InvalidArgumentException $e) {
                $this->addFlash('error', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_schedule_index');
    }

    #[Route('/exceptions/nouvelle', name: 'app_schedule_exception_new', methods: ['GET', 'POST'])]
    public function newException(Request $request): Response
    {
        $form = $this->createForm(ScheduleExceptionType::class, [
            'date' => new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris')),
            'kind' => DayKind::Closed->value,
            'label' => DayKind::Closed->defaultLabel(),
            'ranges' => ScheduleFormHelper::rangesForForm([]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->persistException($form->getData())) {
                return $this->redirectToRoute('app_schedule_index');
            }
        }

        return $this->render('schedule/exception_form.html.twig', [
            'form' => $form,
            'title' => 'Nouvelle exception',
            'isEdit' => false,
        ]);
    }

    #[Route('/exceptions/{id}/editer', name: 'app_schedule_exception_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editException(Request $request, ScheduleException $exception): Response
    {
        $serialized = $this->availabilityService->serializeException($exception);
        $form = $this->createForm(ScheduleExceptionType::class, [
            'date' => $exception->getDate(),
            'kind' => $exception->getKind()->value,
            'label' => $serialized['label'] ?? '',
            'ranges' => ScheduleFormHelper::rangesForForm($serialized['ranges'] ?? []),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->persistException($form->getData(), $exception->getId())) {
                return $this->redirectToRoute('app_schedule_index');
            }
        }

        return $this->render('schedule/exception_form.html.twig', [
            'form' => $form,
            'title' => 'Modifier l’exception',
            'isEdit' => true,
            'exception' => $exception,
        ]);
    }

    #[Route('/exceptions/{id}', name: 'app_schedule_exception_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteException(Request $request, ScheduleException $exception): Response
    {
        if ($this->isCsrfTokenValid('delete_exception'.$exception->getId(), (string) $request->request->get('_token'))) {
            $this->manageSchedule->deleteException($exception);
            $this->addFlash('success', 'Exception supprimée.');
        }

        return $this->redirectToRoute('app_schedule_index');
    }

    #[Route('/blocages/nouveau', name: 'app_schedule_block_new', methods: ['GET', 'POST'])]
    public function newBlock(Request $request): Response
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'));
        $start = $now->setTime((int) $now->format('H'), 0);
        $form = $this->createForm(BlockedSlotType::class, [
            'start' => $start,
            'end' => $start->modify('+1 hour'),
            'reason' => '',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->persistBlock($form->getData())) {
                return $this->redirectToRoute('app_schedule_index');
            }
        }

        return $this->render('schedule/block_form.html.twig', [
            'form' => $form,
            'title' => 'Bloquer un créneau',
            'isEdit' => false,
        ]);
    }

    #[Route('/blocages/{id}/editer', name: 'app_schedule_block_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editBlock(Request $request, BlockedSlot $slot): Response
    {
        $form = $this->createForm(BlockedSlotType::class, [
            'start' => $slot->getStartAt(),
            'end' => $slot->getEndAt(),
            'reason' => $slot->getReason() ?? '',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->persistBlock($form->getData(), $slot)) {
                return $this->redirectToRoute('app_schedule_index');
            }
        }

        return $this->render('schedule/block_form.html.twig', [
            'form' => $form,
            'title' => 'Modifier le créneau bloqué',
            'isEdit' => true,
            'slot' => $slot,
        ]);
    }

    #[Route('/blocages/{id}', name: 'app_schedule_block_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deleteBlock(Request $request, BlockedSlot $slot): Response
    {
        if ($this->isCsrfTokenValid('delete_block'.$slot->getId(), (string) $request->request->get('_token'))) {
            $this->manageSchedule->deleteBlockedSlot($slot);
            $this->addFlash('success', 'Créneau bloqué supprimé.');
        }

        return $this->redirectToRoute('app_schedule_index');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistException(array $data, ?int $id = null): bool
    {
        $kind = DayKind::tryFrom((string) ($data['kind'] ?? ''));
        if (!$kind instanceof DayKind) {
            $this->addFlash('error', 'Statut invalide.');

            return false;
        }

        try {
            $ranges = $kind === DayKind::Open
                ? ScheduleFormHelper::rangesToPayload($data['ranges'] ?? [])
                : [];
            $this->manageSchedule->upsertException(
                $data['date'],
                $kind,
                $ranges,
                isset($data['label']) ? (string) $data['label'] : null,
                $id,
            );
            $this->addFlash('success', 'Exception enregistrée.');

            return true;
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return false;
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function persistBlock(array $data, ?BlockedSlot $slot = null): bool
    {
        try {
            if ($slot instanceof BlockedSlot) {
                $this->manageSchedule->updateBlockedSlot(
                    $slot,
                    $data['start'] ?? null,
                    $data['end'] ?? null,
                    \array_key_exists('reason', $data) ? (string) $data['reason'] : null,
                );
            } else {
                $this->manageSchedule->createBlockedSlot(
                    $data['start'],
                    $data['end'],
                    isset($data['reason']) && $data['reason'] !== '' ? (string) $data['reason'] : null,
                );
            }
            $this->addFlash('success', 'Créneau bloqué enregistré.');

            return true;
        } catch (\InvalidArgumentException|\Exception $e) {
            $this->addFlash('error', $e->getMessage());

            return false;
        }
    }
}
