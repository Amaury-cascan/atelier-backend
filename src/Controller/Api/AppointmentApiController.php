<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use DateInterval;

#[Route('/api/appointment')]
class AppointmentApiController extends AbstractController
{
    #[Route('/list', name: 'app_appointment_list', methods: ['GET'])]
    public function listAppointments(EntityManagerInterface $entityManager): JsonResponse
    {
        // Récupérer tous les rendez-vous
        $appointments = $entityManager->getRepository(Appointment::class)->findAll();

        // Transformer les rendez-vous pour ne retourner que les dates et les heures
        $appointmentsArray = array_map(function($appointment) {
            return [
                'date' => $appointment->getDate()->format('Y-m-d H:i:s'),  // Date et heure de début
                'endDate' => $appointment->getEndDate()->format('Y-m-d H:i:s'),  // Date et heure de fin
            ];
        }, $appointments);

        // Retourner la réponse en JSON
        return new JsonResponse([
            'success' => true,
            'appointments' => $appointmentsArray,
        ]);
    }
    #[Route('/create', name: 'app_appointment_api_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        // Validation des données
        if (empty($data['date']) || empty($data['serviceId']) || empty($data['clientId'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données manquantes'], 400);
        }

        $startDate = new \DateTime($data['date']);

        $service = $entityManager->getRepository(Service::class)->find($data['serviceId']);
        $user = $entityManager->getRepository(User::class)->find($data['clientId']);

        if (!$service || !$user) {
            return new JsonResponse(['success' => false, 'message' => 'Service ou Utilisateur introuvable'], 404);
        }

        $appointment = new Appointment();
        $appointment->setDate($startDate);
        $appointment->setService($service);
        $appointment->setClient($user);

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
            'clientFirstName' => $user->getFirstName(),
            'clientName' => $user->getName(),
            'clientId' => $user->getId()
        ]);
    }

    #[Route('/user', name: 'app_user_appointments', methods: ['GET'])]
    public function getUserAppointments(EntityManagerInterface $entityManager, Security $security): JsonResponse
    {
        $user = $security->getUser();
        if (!$user) {
            return $this->json(['success' => false, 'message' => 'Utilisateur non connecté'], 401);
        }

        $appointments = $entityManager->getRepository(Appointment::class)->findBy(['client' => $user]);

        $appointmentsArray = array_map(function($appointment) {
            return [
                'id' => $appointment->getId(),
                'date' => $appointment->getDate()->format('Y-m-d\TH:i:s'),
                'endDate' => $appointment->getEndDate()->format('Y-m-d\TH:i:s'),
                'serviceName' => $appointment->getService()->getName(),
                'serviceId' => $appointment->getService()->getId(),
            ];
        }, $appointments);

        return $this->json([
            'success' => true,
            'appointments' => $appointmentsArray
        ]);
    }


}