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
                'title' => $appointment->getService()->getName(), // Utilisez le nom du service comme titre
                'start' => $appointment->getDate()->format('Y-m-d\TH:i:s'),
                'end' => $appointment->getEndDate()->format('Y-m-d\TH:i:s'),
                'serviceId' => $appointment->getService()->getId(),
                'clientId' => $appointment->getClient()->getId(),
                'extendedProps' => [
                    'clientName' => $appointment->getClient()->getName(),
                    'clientFirstName' => $appointment->getClient()->getFirstName()
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

        // Calculer la date de fin en ajoutant la durée du service
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
            'serviceName' => $service->getName(),
            'serviceId' => $service->getId(),
            'clientFirstName' =>$client->getFirstName(),
            'clientName' => $client->getName(),
            'clientId' => $client->getId()
        ]);
    }

    #[Route('/{id}/editer', name: 'app_appointment_edit', methods: ['POST'])]
    public function edit(Request $request, Appointment $appointment, EntityManagerInterface $entityManager): Response
    {
        // Récupérer les données du formulaire
        $data = $request->request->all();
        // Validation des données
        if (empty($data['date']) || empty($data['serviceId'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données manquantes'], 400);
        }
    
        // Convertir la date reçue en temps local (GMT+2)
        $date = new \DateTime($data['date']);
        
        // Récupérer le service
        $service = $entityManager->getRepository(Service::class)->find($data['serviceId']);
        if (!$service) {
            return new JsonResponse(['success' => false, 'message' => 'Service introuvable'], 404);
        }
    
        // Mettre à jour les informations du rendez-vous
        $appointment->setDate($date);
    
        // Calculer la date de fin en ajoutant la durée du service
        $endDate = clone $date; // Cloner la date de début
        $endDate->add(new DateInterval('PT' . $service->getDuration() . 'M')); // Ajouter la durée du service
        $appointment->setEndDate($endDate);
    
        // Enregistrer les modifications
        $entityManager->flush();
    
        return $this->redirectToRoute('app_client_appointment', ['id' => $appointment->getClient()->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/update', name: 'app_appointment_update', methods: ['POST'])]
    public function update(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        // Validation des données
        if (empty($data['id']) || empty($data['date']) || empty($data['serviceId']) || empty($data['clientId'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données manquantes'], 400);
        }

        // Convertir la date reçue en temps local (GMT+2)
        $date = new \DateTime($data['date']);
        $date->setTimezone(new \DateTimeZone('Europe/Paris')); // Définir le fuseau horaire GMT+2

        $appointment = $entityManager->getRepository(Appointment::class)->find($data['id']);
        if (!$appointment) {
            return new JsonResponse(['success' => false, 'message' => 'Rendez-vous non trouvé'], 404);
        }

        $service = $entityManager->getRepository(Service::class)->find($data['serviceId']);
        $client = $entityManager->getRepository(Client::class)->find($data['clientId']);

        if (!$service || !$client) {
            return new JsonResponse(['success' => false, 'message' => 'Service ou Client introuvable'], 404);
        }

        $appointment->setDate($date);
        $appointment->setService($service);
        $appointment->setClient($client);

        // Calculer la date de fin en ajoutant la durée du service
        if ($data['endDate']) {
            $endDate = new \DateTime($data['endDate']);
            $endDate->setTimezone(new \DateTimeZone('Europe/Paris'));
        } else {
            $endDate = clone $date;
            $endDate->add(new DateInterval('PT' . $service->getDuration() . 'M'));
        }
        
        $appointment->setEndDate($endDate);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $appointment->getId(),
            'date' => $appointment->getDate()->format('Y-m-d\TH:i:s'),
            'endDate' => $appointment->getEndDate()->format('Y-m-d\TH:i:s'),
            'serviceName' => $service->getName(),
            'clientFirstName' =>$client->getFirstName(),
            'clientName' => $client->getName(),
            'serviceId' => $service->getId(),
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
                'duration' => $service->getDuration() // Assurez-vous que cette méthode existe dans votre entité Service
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