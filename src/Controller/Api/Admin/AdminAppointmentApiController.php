<?php

namespace App\Controller\Api\Admin;

use App\Entity\Appointment;
use App\Entity\Client;
use App\Entity\Service;
use App\Repository\AppointmentRepository;
use App\Repository\ClientRepository;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/appointments')]
class AdminAppointmentApiController extends AbstractController
{
    public function __construct(
        private readonly AppointmentRepository $appointmentRepository,
        private readonly ServiceRepository $serviceRepository,
        private readonly ClientRepository $clientRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'api_admin_appointments_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $events = array_map(fn (Appointment $appointment) => $this->serialize($appointment), $this->appointmentRepository->findAll());

        return $this->json(['success' => true, 'appointments' => $events]);
    }

    #[Route('/meta', name: 'api_admin_appointments_meta', methods: ['GET'])]
    public function meta(): JsonResponse
    {
        $services = array_map(static fn (Service $service) => [
            'id' => $service->getId(),
            'name' => $service->getName(),
            'duration' => $service->getDuration(),
            'price' => $service->getPrice(),
            'active' => $service->isActive() !== false,
        ], $this->serviceRepository->findBy([], ['name' => 'ASC']));

        $clients = array_map(static fn (Client $client) => [
            'id' => $client->getId(),
            'name' => $client->getName(),
            'firstName' => $client->getFirstName(),
        ], $this->clientRepository->findBy([], ['name' => 'ASC']));

        return $this->json(['success' => true, 'services' => $services, 'clients' => $clients]);
    }

    #[Route('', name: 'api_admin_appointments_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $service = $this->serviceRepository->find($data['serviceId'] ?? 0);
        $client = $this->clientRepository->find($data['clientId'] ?? 0);

        if (!$service instanceof Service || !$client instanceof Client) {
            return $this->json(['success' => false, 'message' => 'Service ou cliente introuvable.'], Response::HTTP_BAD_REQUEST);
        }
        if (empty($data['date'])) {
            return $this->json(['success' => false, 'message' => 'Date obligatoire.'], Response::HTTP_BAD_REQUEST);
        }

        $tz = new \DateTimeZone('Europe/Paris');
        $start = new \DateTimeImmutable((string) $data['date'], $tz);
        if (!empty($data['endDate'])) {
            $end = new \DateTimeImmutable((string) $data['endDate'], $tz);
        } else {
            $end = $start->modify('+' . max(5, (int) $service->getDuration()) . ' minutes');
        }

        $appointment = new Appointment();
        $appointment->setService($service);
        $appointment->setClient($client);
        $appointment->setDate(\DateTime::createFromImmutable($start));
        $appointment->setEndDate(\DateTime::createFromImmutable($end));
        $appointment->setPrice(isset($data['price']) ? (int) $data['price'] : $service->getPrice());

        $this->entityManager->persist($appointment);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'appointment' => $this->serialize($appointment)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_appointments_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(Appointment $appointment, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $tz = new \DateTimeZone('Europe/Paris');

        if (!empty($data['serviceId'])) {
            $service = $this->serviceRepository->find($data['serviceId']);
            if (!$service instanceof Service) {
                return $this->json(['success' => false, 'message' => 'Service introuvable.'], Response::HTTP_BAD_REQUEST);
            }
            $appointment->setService($service);
        }
        if (!empty($data['clientId'])) {
            $client = $this->clientRepository->find($data['clientId']);
            if (!$client instanceof Client) {
                return $this->json(['success' => false, 'message' => 'Cliente introuvable.'], Response::HTTP_BAD_REQUEST);
            }
            $appointment->setClient($client);
        }
        if (!empty($data['date'])) {
            $appointment->setDate(\DateTime::createFromImmutable(new \DateTimeImmutable((string) $data['date'], $tz)));
        }
        if (!empty($data['endDate'])) {
            $appointment->setEndDate(\DateTime::createFromImmutable(new \DateTimeImmutable((string) $data['endDate'], $tz)));
        } elseif (!empty($data['date']) && $appointment->getService()) {
            $start = \DateTimeImmutable::createFromMutable($appointment->getDate());
            $appointment->setEndDate(\DateTime::createFromImmutable(
                $start->modify('+' . max(5, (int) $appointment->getService()->getDuration()) . ' minutes')
            ));
        }
        if (array_key_exists('price', $data) && $data['price'] !== null) {
            $appointment->setPrice((int) $data['price']);
        }

        $this->entityManager->flush();

        return $this->json(['success' => true, 'appointment' => $this->serialize($appointment)]);
    }

    #[Route('/{id}', name: 'api_admin_appointments_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Appointment $appointment): JsonResponse
    {
        $this->entityManager->remove($appointment);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Rendez-vous supprimé.']);
    }

    private function serialize(Appointment $appointment): array
    {
        $client = $appointment->getClient();

        return [
            'id' => $appointment->getId(),
            'title' => $appointment->getService()?->getName() ?? 'Rendez-vous',
            'date' => $appointment->getDate()?->format('Y-m-d\TH:i:s'),
            'endDate' => $appointment->getEndDate()?->format('Y-m-d\TH:i:s'),
            'price' => $appointment->getPrice(),
            'duration' => $appointment->getService()?->getDuration(),
            'serviceId' => $appointment->getService()?->getId(),
            'serviceName' => $appointment->getService()?->getName(),
            'clientId' => $client?->getId(),
            'clientName' => method_exists($client, 'getName') ? $client->getName() : null,
            'clientFirstName' => method_exists($client, 'getFirstName') ? $client->getFirstName() : null,
        ];
    }
}
