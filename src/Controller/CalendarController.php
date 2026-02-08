<?php
namespace App\Controller;

use App\Entity\Appointment;
use App\Repository\AppointmentRepository;
use App\Repository\ServiceRepository;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/administration/calendrier')]
class CalendarController extends AbstractController
{
    #[Route('/', name: 'app_calendar')]
    public function index(AppointmentRepository $appointmentRepository)
    {
        // Calculer la semaine courante
        $today = new \DateTime();
        $currentWeekStart = clone $today;
        $currentWeekStart->modify('monday this week');
        
        $currentWeekEnd = clone $currentWeekStart;
        $currentWeekEnd->modify('+6 days');
        
        // Générer les 7 jours de la semaine
        $weekDays = [];
        for ($i = 0; $i < 7; $i++) {
            $day = clone $currentWeekStart;
            $day->modify('+' . $i . ' days');
            $weekDays[] = $day;
        }
        
        // Récupérer les rendez-vous de cette semaine
        $appointmentsThisWeek = $appointmentRepository->findAppointmentsBetweenDates(
            $currentWeekStart,
            $currentWeekEnd
        );
        
        return $this->render('calendar/index.html.twig', [
            'currentWeekStart' => $currentWeekStart,
            'currentWeekEnd' => $currentWeekEnd,
            'weekDays' => $weekDays,
            'appointmentsThisWeek' => $appointmentsThisWeek,
        ]);
    }

    #[Route('/events', name: 'app_calendar_events', methods: ['GET'])]
    public function getEvents(AppointmentRepository $appointmentRepository): JsonResponse
    {
        $appointments = $appointmentRepository->findAll();
        $events = [];
        
        foreach ($appointments as $appointment) {
            $events[] = [
                'id' => $appointment->getId(),
                'title' => $appointment->getService()?->getName(),
                'start' => $appointment->getDate()->format('Y-m-d\TH:i:s'),
                'end' => $appointment->getEndDate()->format('Y-m-d\TH:i:s'),
                'extendedProps' => [
                    'clientName' => $appointment->getClient()->getName(),
                    'clientFirstName' => $appointment->getClient()->getFirstName(),
                    'serviceId' => $appointment->getService()?->getId(),
                    'clientId' => $appointment->getClient()->getId(),
                    'serviceName' => $appointment->getService()?->getName(),
                    'price' => $appointment->getPrice(),
                    'duration' => $appointment->getService()?->getDuration(),
                ]
            ];
        }
        
        return new JsonResponse($events);
    }

    #[Route('/services', name: 'app_get_services', methods: ['GET'])]
    public function getServices(ServiceRepository $serviceRepository): JsonResponse
    {
        $services = $serviceRepository->findAll();
        $data = [];
        
        foreach ($services as $service) {
            $data[] = [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'duration' => $service->getDuration(),
                'price' => $service->getPrice(),
            ];
        }
        
        return new JsonResponse($data);
    }

    #[Route('/clients', name: 'app_get_clients', methods: ['GET'])]
    public function getClients(ClientRepository $clientRepository): JsonResponse
    {
        $clients = $clientRepository->findAll();
        $data = [];
        
        foreach ($clients as $client) {
            $data[] = [
                'id' => $client->getId(),
                'name' => $client->getName(),
                'firstName' => $client->getFirstName(),
            ];
        }
        
        return new JsonResponse($data);
    }

    #[Route('/appointment/create', name: 'app_appointment_create', methods: ['POST'])]
    public function createAppointment(
        Request $request, 
        EntityManagerInterface $em,
        ServiceRepository $serviceRepository,
        ClientRepository $clientRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        $service = $serviceRepository->find($data['serviceId']);
        $client = $clientRepository->find($data['clientId']);
        
        if (!$service || !$client) {
            return new JsonResponse(['success' => false, 'error' => 'Service ou client introuvable'], 400);
        }
        
        $appointment = new Appointment();
        
        // Créer les dates avec le fuseau horaire européen
        $timezone = new \DateTimeZone('Europe/Paris');
        $startDate = new \DateTime($data['date'], $timezone);
        $endDate = new \DateTime($data['endDate'], $timezone);
        
        $appointment->setDate($startDate);
        $appointment->setEndDate($endDate);
        $appointment->setService($service);
        $appointment->setClient($client);
        
        $em->persist($appointment);
        $em->flush();
        
        return new JsonResponse([
            'success' => true,
            'id' => $appointment->getId(),
            'serviceName' => $service->getName(),
            'clientName' => $client->getName(),
            'clientFirstName' => $client->getFirstName(),
            'serviceId' => $service->getId(),
            'clientId' => $client->getId(),
            'date' => $appointment->getDate()->format('Y-m-d\TH:i:s'),
            'endDate' => $appointment->getEndDate()->format('Y-m-d\TH:i:s'),
        ]);
    }

    #[Route('/appointment/update', name: 'app_appointment_update', methods: ['POST'])]
    public function updateAppointment(
        Request $request, 
        EntityManagerInterface $em,
        AppointmentRepository $appointmentRepository,
        ServiceRepository $serviceRepository,
        ClientRepository $clientRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        $appointment = $appointmentRepository->find($data['id']);
        if (!$appointment) {
            return new JsonResponse(['success' => false, 'error' => 'Rendez-vous introuvable'], 404);
        }
        
        $service = $serviceRepository->find($data['serviceId']);
        $client = $clientRepository->find($data['clientId']);
        
        if (!$service || !$client) {
            return new JsonResponse(['success' => false, 'error' => 'Service ou client introuvable'], 400);
        }
        
        $appointment->setDate(new \DateTime($data['date']));
        $appointment->setEndDate(new \DateTime($data['endDate']));
        $appointment->setService($service);
        $appointment->setClient($client);
        if (isset($data['price']) && (int) $data['price'] >= 0) {
            $appointment->setPrice((int) $data['price']);
        }
        
        $em->flush();
        
        return new JsonResponse([
            'success' => true,
            'id' => $appointment->getId(),
            'serviceName' => $service->getName(),
            'clientName' => $client->getName(),
            'clientFirstName' => $client->getFirstName(),
            'serviceId' => $service->getId(),
            'clientId' => $client->getId(),
            'date' => $appointment->getDate()->format('Y-m-d\TH:i:s'),
            'endDate' => $appointment->getEndDate()->format('Y-m-d\TH:i:s'),
            'price' => $appointment->getPrice(),
        ]);
    }

    #[Route('/appointment/delete', name: 'app_appointment_delete', methods: ['POST'])]
    public function deleteAppointment(
        Request $request, 
        EntityManagerInterface $em,
        AppointmentRepository $appointmentRepository
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        $appointment = $appointmentRepository->find($data['id']);
        if (!$appointment) {
            return new JsonResponse(['success' => false, 'error' => 'Rendez-vous introuvable'], 404);
        }
        
        $em->remove($appointment);
        $em->flush();
        
        return new JsonResponse(['success' => true]);
    }

    #[Route('/creer-creneau', name: 'app_calendar_create_slot', methods: ['POST'])]
    public function createSlot(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        // Logique pour sauvegarder le nouveau créneau en base de données
        // Utilisez $data['debut'] et $data['fin'] pour accéder aux données

        return new JsonResponse(['succes' => true]);
    }
}