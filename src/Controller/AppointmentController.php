<?php
namespace App\Controller;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\Client;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use DateInterval;

#[Route('/administration/appointment')]
class AppointmentController extends AbstractController
{
    #[Route('/events', name: 'app_calendar_events')]
    public function getEvents(EntityManagerInterface $entityManager): JsonResponse
    {
        $appointments = $entityManager->getRepository(Appointment::class)->findAll();
        $events = [];
        foreach ($appointments as $appointment) {
            $events[] = [
                'id' => $appointment->getId(),
                'title' => $appointment->getService()?->getName(),
                'start' => $appointment->getDate()->format('Y-m-d\TH:i:s'),
                'end' => $appointment->getEndDate()->format('Y-m-d\TH:i:s'),
                'serviceId' => $appointment->getService()?->getId(),
                'clientId' => $appointment->getClient()->getId(),
                'extendedProps' => [
                    'clientName' => $appointment->getClient()->getName(),
                    'clientFirstName' => $appointment->getClient()->getFirstName(),
                    'serviceName' => $appointment->getService()?->getName(),
                    'price' => $appointment->getPrice(),
                ]
            ];
        }
        return new JsonResponse($events);
    }

    #[Route('/create', name: 'app_appointment_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        // Validation des données
        if (empty($data['date']) || empty($data['serviceId']) || empty($data['clientId'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données manquantes'], 400);
        }

        // Convertir la date reçue en temps local (GMT+2)
        $startDate = new \DateTime($data['date']);

        $service = $entityManager->getRepository(Service::class)->find($data['serviceId']);
        $client = $entityManager->getRepository(Client::class)->find($data['clientId']);

        if (!$service || !$client) {
            return new JsonResponse(['success' => false, 'message' => 'Service ou Client introuvable'], 404);
        }

        $appointment = new Appointment();
        $appointment->setDate($startDate);
        $appointment->setService($service);
        $appointment->setClient($client);
        $appointment->setPrice($service->getPrice());

        $endDate = clone $startDate;
        $endDate->add(new DateInterval('PT' . $service->getDuration() . 'M'));
        $appointment->setEndDate($endDate);

        $entityManager->persist($appointment);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $appointment->getId(),
            'date' => $appointment->getDate()->format('Y-m-d\TH:i:s'),
            'endDate' => $appointment->getEndDate()->format('Y-m-d\TH:i:s'),
            'serviceName' => $appointment->getService()?->getName(),
            'serviceId' => $service->getId(),
            'price' => $appointment->getPrice(),
            'clientFirstName' => $client->getFirstName(),
            'clientName' => $client->getName(),
            'clientId' => $client->getId()
        ]);
    }

    #[Route('/{id}/editer', name: 'app_appointment_edit', methods: ['POST'])]
    public function edit(Request $request, Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        $data = $request->request->all();
        if (empty($data['date'])) {
            return new JsonResponse(['success' => false, 'message' => 'Date manquante'], 400);
        }

        $date = new \DateTime($data['date']);
        $appointment->setDate($date);

        if (!empty($data['serviceId'])) {
            $service = $entityManager->getRepository(Service::class)->find($data['serviceId']);
            if ($service) {
                $appointment->setService($service);
                $appointment->setPrice($service->getPrice());
                $endDate = clone $date;
                $endDate->add(new DateInterval('PT' . $service->getDuration() . 'M'));
                $appointment->setEndDate($endDate);
            }
        } else {
            $appointment->setService(null);
            if (isset($data['price'])) {
                $appointment->setPrice((int) $data['price']);
            }
            $duration = $appointment->getService()?->getDuration() ?? 60;
            $endDate = clone $date;
            $endDate->add(new DateInterval('PT' . $duration . 'M'));
            $appointment->setEndDate($endDate);
        }

        if (isset($data['price']) && (int) $data['price'] >= 0) {
            $appointment->setPrice((int) $data['price']);
        }

        $entityManager->flush();

        return $this->redirectToRoute('app_client_appointment', ['id' => $appointment->getClient()->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/update', name: 'app_appointment_update', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (empty($data['id']) || empty($data['date']) || empty($data['clientId'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données manquantes'], 400);
        }

        $date = new \DateTime($data['date']);
        $date->setTimezone(new \DateTimeZone('Europe/Paris'));

        $appointment = $entityManager->getRepository(Appointment::class)->find($data['id']);
        if (!$appointment) {
            return new JsonResponse(['success' => false, 'message' => 'Rendez-vous non trouvé'], 404);
        }

        $client = $entityManager->getRepository(Client::class)->find($data['clientId']);
        if (!$client) {
            return new JsonResponse(['success' => false, 'message' => 'Client introuvable'], 404);
        }

        $appointment->setDate($date);
        $appointment->setClient($client);

        if (!empty($data['serviceId'])) {
            $service = $entityManager->getRepository(Service::class)->find($data['serviceId']);
            if ($service) {
                $appointment->setService($service);
                $appointment->setPrice($data['price'] ?? $service->getPrice());
                $endDate = isset($data['endDate']) ? new \DateTime($data['endDate']) : (clone $date)->add(new DateInterval('PT' . $service->getDuration() . 'M'));
                $appointment->setEndDate($endDate);
            }
        } else {
            $appointment->setService(null);
            if (isset($data['price'])) {
                $appointment->setPrice((int) $data['price']);
            }
            $duration = $appointment->getService()?->getDuration() ?? 60;
            $endDate = isset($data['endDate']) ? new \DateTime($data['endDate']) : (clone $date)->add(new DateInterval('PT' . $duration . 'M'));
            $appointment->setEndDate($endDate);
        }

        if (isset($data['price']) && (int) $data['price'] >= 0) {
            $appointment->setPrice((int) $data['price']);
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $appointment->getId(),
            'date' => $appointment->getDate()->format('Y-m-d\TH:i:s'),
            'endDate' => $appointment->getEndDate()->format('Y-m-d\TH:i:s'),
            'serviceName' => $appointment->getService()?->getName(),
            'serviceId' => $appointment->getService()?->getId(),
            'price' => $appointment->getPrice(),
            'clientFirstName' => $client->getFirstName(),
            'clientName' => $client->getName(),
            'clientId' => $client->getId()
        ]);
    }

    #[Route('/delete', name: 'app_appointment_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        // Validation des données
        if (empty($data['id'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données manquantes'], 400);
        }

        $appointment = $entityManager->getRepository(Appointment::class)->find($data['id']);
        if (!$appointment) {
            return new JsonResponse(['success' => false, 'message' => 'Rendez-vous non trouvé'], 404);
        }

        try {
            $entityManager->remove($appointment);
            $entityManager->flush();
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'message' => 'Erreur lors de la suppression: ' . $e->getMessage()], 500);
        }
    }

    #[Route('/{id}', name: 'app_app_delete', methods: ['POST'])]
    public function del(Request $request, Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$appointment->getId(), $request->request->get('_token'))) {
            $entityManager->remove($appointment);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_client_appointment', ['id' => $appointment->getClient()->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/services', name: 'app_get_services')]
    public function getServices(EntityManagerInterface $entityManager): JsonResponse
    {
        $services = $entityManager->getRepository(Service::class)->findAll();
        $servicesArray = array_map(function($service) {
            return [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'duration' => $service->getDuration(),
                'price' => $service->getPrice(),
            ];
        }, $services);
        return new JsonResponse($servicesArray);
    }

    #[Route('/clients', name: 'app_get_clients')]
    public function getClients(EntityManagerInterface $entityManager): JsonResponse
    {
        $clients = $entityManager->getRepository(Client::class)->findAll();
        $clientsArray = array_map(function($client) {
            return ['id' => $client->getId(),'firstName' => $client->getFirstName(), 'name' => $client->getName()];
        }, $clients);
        return new JsonResponse($clientsArray);
    }
}