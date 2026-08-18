<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\User;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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

        // Retourne uniquement les créneaux occupés (sans identifiant client)
        // pour permettre au front d'éviter les doubles réservations.
        $appointmentsArray = array_map(function($appointment) {
            return [
                'date' => $appointment->getDate()->format('Y-m-d H:i:s'),
                'endDate' => $appointment->getEndDate()->format('Y-m-d H:i:s'),
                'service' => $appointment->getService()?->getName(),
            ];
        }, $appointments);

        // Retourner la réponse en JSON
        return new JsonResponse([
            'success' => true,
            'appointments' => $appointmentsArray,
        ]);
    }
    #[Route('/create', name: 'app_appointment_api_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, EmailService $emailService): JsonResponse
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
        $appointment->setPrice($service->getPrice());

        $endDate = clone $startDate;
        $endDate->add(new DateInterval('PT' . $service->getDuration() . 'M'));
        $appointment->setEndDate($endDate);

        $entityManager->persist($appointment);
        $entityManager->flush();

        try {
            // Utilisation du nouveau service EmailService
            $emailService->sendRdvToClient($appointment->getDate()->format('d-m-Y \à H:i'), $user->getFirstName(), $user->getName(), $user->getEmail(), $service->getName());
            $emailService->sendRdvToMarie($appointment->getDate()->format('d-m-Y \à H:i'), $user->getFirstName(), $user->getName(), $service->getName());
        } catch (\Exception $e) {
            // Log l'erreur mais ne pas empêcher la création du compte
            //$this->logger->error('Error sending welcome email: ' . $e->getMessage());
            // Optionnel : informer l'utilisateur que l'email n'a pas pu être envoyé
            new JsonResponse([ 'message' => 'Account created successfully, but welcome email could not be sent.' ], Response::HTTP_CREATED);
        }
        return new JsonResponse([
            'success' => true,
            'id' => $appointment->getId(),
            'date' => $appointment->getDate()->format('Y-m-d\TH:i:s'),
            'endDate' => $appointment->getEndDate()->format('Y-m-d\TH:i:s'),
            'serviceName' => $service->getName(),
            'serviceId' => $service->getId(),
            'price' => $appointment->getPrice(),
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
                'serviceName' => $appointment->getService()?->getName(),
                'serviceId' => $appointment->getService()?->getId(),
                'price' => $appointment->getPrice(),
            ];
        }, $appointments);

        return $this->json([
            'success' => true,
            'appointments' => $appointmentsArray
        ]);
    }

    #[Route('/{id}/duration', name: 'app_appointment_update_duration', methods: ['PATCH'])]
    public function updateDuration(int $id, Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        // Validation
        if (!isset($data['duration']) || !is_numeric($data['duration']) || $data['duration'] < 5) {
            return new JsonResponse(['success' => false, 'message' => 'Durée invalide'], 400);
        }

        $appointment = $entityManager->getRepository(Appointment::class)->find($id);
        if (!$appointment) {
            return new JsonResponse(['success' => false, 'message' => 'Rendez-vous introuvable'], 404);
        }

        // Calculer la nouvelle heure de fin
        $newEndDate = clone $appointment->getDate();
        $newEndDate->add(new DateInterval('PT' . intval($data['duration']) . 'M'));
        
        $appointment->setEndDate($newEndDate);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'id' => $appointment->getId(),
            'duration' => $data['duration'],
            'endDate' => $appointment->getEndDate()->format('Y-m-d\TH:i:s')
        ]);
    }

    #[Route('/{id}', name: 'app_appointment_delete', methods: ['DELETE'])]
    public function deleteAppointment(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $appointment = $entityManager->getRepository(Appointment::class)->find($id);
        if (!$appointment) {
            return new JsonResponse(['success' => false, 'message' => 'Rendez-vous introuvable'], 404);
        }

        $entityManager->remove($appointment);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Rendez-vous supprimé avec succès'
        ]);
    }


}