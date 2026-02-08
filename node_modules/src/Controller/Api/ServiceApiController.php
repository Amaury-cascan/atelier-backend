<?php

namespace App\Controller\Api;

use App\Entity\Service;
use App\Repository\ServiceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/api/services')]
class ServiceApiController extends AbstractController
{
    private ServiceRepository $serviceRepository;

    public function __construct(ServiceRepository $serviceRepository)
    {
        $this->serviceRepository = $serviceRepository;
    }

    #[Route('/', name: 'api_service_index', methods: ['GET'])]
    public function findAll(): JsonResponse
    {
        $services = $this->serviceRepository->findAll();

        // Convertit les services en tableau associatif
        $data = [];
        foreach ($services as $service) {
            $data[] = [
                'id' => $service->getId(),
                'category' => $service->getCategory()->getName(),
                'name' => $service->getName(),
                'price' => $service->getPrice(),
                'picture' => $service->getPicture(),
                'description' => $service->getDescription(),
                'duration' => $service->getDuration(),
                'active' => $service->isActive(),

            ];
        }

        return $this->json($data, 200, [], ['groups' => 'category_detail']);
    }

    #[Route('/{id}', name: 'api_service_show', methods: ['GET'])]
    public function find(Request $request, int $id): JsonResponse
    {
        $service = $this->serviceRepository->find($id);

        if (!$service) {
            return new JsonResponse(['error' => 'Service not found'], Response::HTTP_NOT_FOUND);
        }

        $data = [
            'id' => $service->getId(),
            'category' => $service->getCategory()->getName(),
            'name' => $service->getName(),
            'price' => $service->getPrice(),
            'picture' => $service->getPicture(),
            'description' => $service->getDescription(),
            'duration' => $service->getDuration(),
        ];

        return $this->json($data, 200, [], ['groups' => 'categories']);
    }
}
