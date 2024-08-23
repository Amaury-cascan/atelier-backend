<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\Appointment;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;

#[Route('/administration/client')]
class ClientController extends AbstractController
{
    #[Route('/', name: 'app_client_index', methods: ['GET'])]
    public function index(ClientRepository $clientRepository, Request $request): Response
    {
        $search = $request->query->get('search');
        $sort = $request->query->get('sort');

        $clients = $clientRepository->findAll();

        // Filtrage
        if ($search) {
            $clients = array_filter($clients, function($client) use ($search) {
                return stripos($client->getFirstName(), $search) !== false || stripos($client->getName(), $search) !== false;
            });
        }

        // Tri
        if ($sort) {
            usort($clients, function($a, $b) use ($sort) {
                return strcmp($a->{'get' . ucfirst($sort)}(), $b->{'get' . ucfirst($sort)}());
            });
        }

        return $this->render('client/index.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/nouveau', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $client = new Client();
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($client);
            $entityManager->flush();

            return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('client/new.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_client_show', methods: ['GET'])]
    public function show(Client $client): Response
    {
        return $this->render('client/show.html.twig', [
            'client' => $client,
        ]);
    }
    #[Route('/{id}/rendez-vous', name: 'app_client_appointment', methods: ['GET'])]
    public function appointment(Client $client, EntityManagerInterface $entityManager): Response
    {
        $appointments = $client->getAppointments();
        
        // Récupérer le premier rendez-vous ou un rendez-vous spécifique si nécessaire
        $appointment = $appointments ? $appointments[0] : null; // Par exemple, prendre le premier rendez-vous
        // Définir la date actuelle
        $currentDate = new \DateTime(); // Ou une date spécifique si nécessaire
        // Récupérer les créneaux disponibles pour le client
        $serviceDuration = $appointment->getService()->getDuration(); // Durée du service en minutes
        $availableSlots = $this->getAvailableSlots($client, $entityManager, $serviceDuration, $currentDate); // Passer les créneaux disponibles à la vue

        return $this->render('client/appointment.html.twig', [
            'client' => $client,
            'appointments' => $appointments,
            'availableSlots' => $availableSlots, // Passer les créneaux disponibles à la vue
            'appointment' => $appointment, // Passer l'objet appointment au template
            'currentDate' => $currentDate, // Passer la date actuelle au template
        ]);
    }
    #[Route('/slots-valides/{id}', name: 'app_available_slots', methods: ['GET'])]
    public function availableSlots(Request $request, EntityManagerInterface $entityManager, Appointment $appointment): JsonResponse
    {
        $dateParam = $request->query->get('date');
        
       if (!$dateParam) {
           return new JsonResponse(['success' => false, 'message' => 'Date manquante'], 400);
       }

       // Assurez-vous que la date est au format correct
       try {
           $date = new \DateTime($dateParam);
       } catch (\Exception $e) {
           return new JsonResponse(['success' => false, 'message' => 'Date invalide'], 400);
       }

       // Récupérer les créneaux disponibles pour l'appointment sélectionné
       $serviceDuration = $appointment->getService()->getDuration(); // Durée du service en minutes
       $availableSlots = $this->getAvailableSlots($appointment->getClient(), $entityManager, $serviceDuration, $date);

       return new JsonResponse(['success' => true, 'slots' => $availableSlots]);
    }

    private function getAvailableSlots(Client $client, EntityManagerInterface $entityManager, int $serviceDuration, ?\DateTime $date = null): array
    {
        // Récupérer tous les rendez-vous existants pour le client
        $appointments = $entityManager->getRepository(Appointment::class)->findAll(); // Récupérer tous les rendez-vous

        // Définir les créneaux de travail (par exemple, de 9h à 17h)
        $workingHours = [
            new \DateTime('09:00'),
            new \DateTime('17:00'),
        ];

        // Si une date est fournie, ajustez les heures de travail
        if ($date) {
            $workingHours[0] = (clone $date)->setTime(9, 0);
            $workingHours[1] = (clone $date)->setTime(17, 0);
        }

        // Créer un tableau pour stocker les créneaux disponibles
        $availableSlots = [];

        // Déterminer les créneaux disponibles
        $currentSlot = clone $workingHours[0];

        while ($currentSlot < $workingHours[1]) {
            // Vérifier si le créneau se chevauche avec un rendez-vous existant
            $isAvailable = true;
            $nextSlot = clone $currentSlot;
            $nextSlot->modify("+{$serviceDuration} minutes"); // Calculer le créneau de fin

            foreach ($appointments as $appointment) {
                // Modifier la condition pour permettre le chevauchement à la fin
                if (
                    ($currentSlot < $appointment->getEndDate() && $nextSlot > $appointment->getDate())
                    && !($currentSlot->format('H:i') === $appointment->getEndDate()->format('H:i'))
                ) {
                    $isAvailable = false;
                    break;
                }
            }

            // Si le créneau est disponible, l'ajouter à la liste
            if ($isAvailable) {
                $availableSlots[] = clone $currentSlot;
            }

            // Passer au créneau suivant
            $currentSlot->modify('+30 minutes');
        }

        return $availableSlots;
    }

    #[Route('/{id}/editer', name: 'app_client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('client/edit.html.twig', [
            'client' => $client,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_client_delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$client->getId(), $request->request->get('_token'))) {
            $entityManager->remove($client);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER);
    }
}