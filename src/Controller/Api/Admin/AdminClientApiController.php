<?php

namespace App\Controller\Api\Admin;

use App\Entity\Appointment;
use App\Entity\Client;
use App\Repository\ClientRepository;
use App\Service\PurgeInactiveClients;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/clients')]
class AdminClientApiController extends AbstractController
{
    public function __construct(
        private readonly ClientRepository $clientRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PurgeInactiveClients $purgeInactiveClients,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    #[Route('', name: 'api_admin_clients_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $clients = $this->clientRepository->findBy([], ['name' => 'ASC']);

        if ($search !== '') {
            $needle = mb_strtolower($search);
            $clients = array_values(array_filter($clients, static function (Client $client) use ($needle) {
                $haystack = mb_strtolower(trim(($client->getFirstName() ?? '') . ' ' . ($client->getName() ?? '') . ' ' . ($client->getEmail() ?? '') . ' ' . ($client->getPhoneNumber() ?? '')));

                return str_contains($haystack, $needle);
            }));
        }

        return $this->json([
            'success' => true,
            'clients' => array_map(fn (Client $client) => $this->serialize($client), $clients),
        ]);
    }

    #[Route('/{id}', name: 'api_admin_clients_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Client $client): JsonResponse
    {
        $appointments = $client->getAppointments()->toArray();
        usort($appointments, static fn (Appointment $a, Appointment $b) => $b->getDate() <=> $a->getDate());

        return $this->json([
            'success' => true,
            'client' => $this->serialize($client),
            'appointments' => array_map(static function (Appointment $appointment) {
                return [
                    'id' => $appointment->getId(),
                    'date' => $appointment->getDate()?->format('Y-m-d\TH:i:s'),
                    'endDate' => $appointment->getEndDate()?->format('Y-m-d\TH:i:s'),
                    'service' => $appointment->getService()?->getName(),
                    'serviceId' => $appointment->getService()?->getId(),
                    'price' => $appointment->getPrice(),
                    'duration' => $appointment->getService()?->getDuration(),
                ];
            }, $appointments),
        ]);
    }

    #[Route('', name: 'api_admin_clients_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        foreach (['name', 'firstName', 'email', 'phoneNumber'] as $field) {
            if (empty($data[$field])) {
                return $this->json(['success' => false, 'message' => "Champ $field obligatoire."], Response::HTTP_BAD_REQUEST);
            }
        }

        $existing = $this->clientRepository->findOneBy(['email' => trim((string) $data['email'])]);
        if ($existing) {
            return $this->json(['success' => false, 'message' => 'Cet email est déjà utilisé.'], Response::HTTP_CONFLICT);
        }

        $client = new Client();
        $client->setName(trim((string) $data['name']));
        $client->setFirstName(trim((string) $data['firstName']));
        $client->setEmail(trim(mb_strtolower((string) $data['email'])));
        $client->setPhoneNumber(trim((string) $data['phoneNumber']));
        $client->setConnu(isset($data['connu']) ? trim((string) $data['connu']) : null);
        $client->setRoles(['ROLE_USER']);

        $plainPassword = !empty($data['password']) ? (string) $data['password'] : bin2hex(random_bytes(8));
        $client->setPassword($this->passwordHasher->hashPassword($client, $plainPassword));

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'client' => $this->serialize($client)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_clients_update', methods: ['PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(Client $client, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        if (isset($data['name'])) {
            $client->setName(trim((string) $data['name']));
        }
        if (isset($data['firstName'])) {
            $client->setFirstName(trim((string) $data['firstName']));
        }
        if (isset($data['email'])) {
            $client->setEmail(trim(mb_strtolower((string) $data['email'])));
        }
        if (isset($data['phoneNumber'])) {
            $client->setPhoneNumber(trim((string) $data['phoneNumber']));
        }
        if (array_key_exists('connu', $data)) {
            $client->setConnu($data['connu'] !== null ? trim((string) $data['connu']) : null);
        }

        $this->entityManager->flush();

        return $this->json(['success' => true, 'client' => $this->serialize($client)]);
    }

    #[Route('/{id}', name: 'api_admin_clients_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Client $client): JsonResponse
    {
        try {
            $reassigned = $this->purgeInactiveClients->deleteClient($client);
        } catch (\DomainException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        return $this->json([
            'success' => true,
            'message' => $reassigned
                ? 'Cliente supprimée. Ses rendez-vous ont été rattachés au compte n°1.'
                : 'Cliente supprimée. Ses rendez-vous ont également été supprimés.',
        ]);
    }

    private function serialize(Client $client): array
    {
        return [
            'id' => $client->getId(),
            'name' => $client->getName(),
            'firstName' => $client->getFirstName(),
            'email' => $client->getEmail(),
            'phoneNumber' => $client->getPhoneNumber(),
            'connu' => $client->getConnu(),
            'appointmentsCount' => $client->getAppointments()->count(),
        ];
    }
}
