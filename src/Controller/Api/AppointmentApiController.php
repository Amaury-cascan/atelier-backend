<?php

namespace App\Controller\Api;

use App\Entity\Appointment;
use App\Entity\Service;
use App\Entity\User;
use App\Repository\AppointmentRepository;
use App\Service\BookAppointment;
use App\Service\BookAppointmentResult;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use DateInterval;

#[Route('/api/appointment')]
class AppointmentApiController extends AbstractController
{
    /** Code retourné au front quand le créneau demandé n'est plus libre. */
    public const SLOT_UNAVAILABLE = 'SLOT_UNAVAILABLE';

    /** Code retourné quand le créneau est hors horaires / bloqué. */
    public const OUTSIDE_HOURS = 'OUTSIDE_HOURS';

    #[Route('/list', name: 'app_appointment_list', methods: ['GET'])]
    public function listAppointments(AppointmentRepository $appointmentRepository): JsonResponse
    {
        // Seuls les rendez-vous non terminés intéressent le calendrier de réservation :
        // inutile d'exposer publiquement tout l'historique, et la charge reste constante.
        $appointments = $appointmentRepository->findUpcoming(
            new \DateTimeImmutable('today', new \DateTimeZone('Europe/Paris'))
        );

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
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        EmailService $emailService,
        BookAppointment $bookAppointment,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        // Validation des données
        if (empty($data['date']) || empty($data['serviceId']) || empty($data['clientId'])) {
            return new JsonResponse(['success' => false, 'message' => 'Données manquantes'], 400);
        }

        try {
            $startDate = new \DateTimeImmutable((string) $data['date']);
        } catch (\Exception) {
            return new JsonResponse(['success' => false, 'message' => 'Date invalide'], 400);
        }

        $service = $entityManager->getRepository(Service::class)->find($data['serviceId']);
        $user = $entityManager->getRepository(User::class)->find($data['clientId']);

        if (!$service || !$user) {
            return new JsonResponse(['success' => false, 'message' => 'Service ou Utilisateur introuvable'], 404);
        }

        $result = $bookAppointment->execute($service, $user, $startDate);

        if (!$result->isBooked()) {
            if ($result->reason === BookAppointmentResult::REASON_OUTSIDE_HOURS) {
                return new JsonResponse([
                    'success' => false,
                    'code' => self::OUTSIDE_HOURS,
                    'message' => 'Ce créneau n\'est pas ouvert à la réservation.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return new JsonResponse([
                'success' => false,
                'code' => self::SLOT_UNAVAILABLE,
                'message' => 'Ce créneau vient d\'être réservé. Merci d\'en choisir un autre.',
            ], Response::HTTP_CONFLICT);
        }

        $appointment = $result->appointment;

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
    public function getUserAppointments(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['success' => false, 'message' => 'Utilisateur non connecté'], Response::HTTP_UNAUTHORIZED);
        }

        $appointments = $entityManager->getRepository(Appointment::class)->findBy(
            ['client' => $user],
            ['date' => 'ASC']
        );

        $appointmentsArray = array_map(static function (Appointment $appointment) {
            return [
                'id' => $appointment->getId(),
                'date' => $appointment->getDate()?->format('Y-m-d\TH:i:s'),
                'endDate' => $appointment->getEndDate()?->format('Y-m-d\TH:i:s'),
                'serviceName' => $appointment->getService()?->getName(),
                'serviceId' => $appointment->getService()?->getId(),
                'price' => $appointment->getPrice(),
            ];
        }, $appointments);

        return $this->json([
            'success' => true,
            'appointments' => $appointmentsArray,
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