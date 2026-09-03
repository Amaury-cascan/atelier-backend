<?php

namespace App\Controller\Api\Admin;

use App\Entity\Category;
use App\Entity\Service;
use App\Repository\CategoryRepository;
use App\Repository\ServiceRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/services')]
class AdminServiceApiController extends AbstractController
{
    public function __construct(
        private readonly ServiceRepository $serviceRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageUploader $imageUploader,
    ) {
    }

    #[Route('', name: 'api_admin_services_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $services = array_map(fn (Service $service) => $this->serialize($service), $this->serviceRepository->findBy([], ['name' => 'ASC']));

        return $this->json(['success' => true, 'services' => $services]);
    }

    #[Route('/{id}', name: 'api_admin_services_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Service $service): JsonResponse
    {
        return $this->json(['success' => true, 'service' => $this->serialize($service)]);
    }

    #[Route('', name: 'api_admin_services_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->extractPayload($request);
        $category = $this->categoryRepository->find($data['categoryId'] ?? 0);
        if (!$category instanceof Category) {
            return $this->json(['success' => false, 'message' => 'Catégorie introuvable.'], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['name']) || !isset($data['price']) || empty($data['duration']) || empty($data['description'])) {
            return $this->json(['success' => false, 'message' => 'Champs obligatoires manquants.'], Response::HTTP_BAD_REQUEST);
        }

        $service = new Service();
        $service->setName(trim((string) $data['name']));
        $service->setPrice((int) $data['price']);
        $service->setDuration((int) $data['duration']);
        $service->setDescription(trim((string) $data['description']));
        $service->setCategory($category);
        $service->setActive($this->toBool($data['active'] ?? true));

        $file = $request->files->get('image');
        if ($file) {
            $service->setPicture($this->imageUploader->upload($file));
        }

        $this->entityManager->persist($service);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'service' => $this->serialize($service)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_services_update', methods: ['POST', 'PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(Service $service, Request $request): JsonResponse
    {
        $data = $this->extractPayload($request);

        if (isset($data['name'])) {
            $service->setName(trim((string) $data['name']));
        }
        if (isset($data['price'])) {
            $service->setPrice((int) $data['price']);
        }
        if (isset($data['duration'])) {
            $service->setDuration((int) $data['duration']);
        }
        if (isset($data['description'])) {
            $service->setDescription(trim((string) $data['description']));
        }
        if (array_key_exists('active', $data)) {
            $service->setActive($this->toBool($data['active']));
        }
        if (!empty($data['categoryId'])) {
            $category = $this->categoryRepository->find($data['categoryId']);
            if (!$category instanceof Category) {
                return $this->json(['success' => false, 'message' => 'Catégorie introuvable.'], Response::HTTP_BAD_REQUEST);
            }
            $service->setCategory($category);
        }

        $file = $request->files->get('image');
        if ($file) {
            $service->setPicture($this->imageUploader->upload($file));
        }

        $this->entityManager->flush();

        return $this->json(['success' => true, 'service' => $this->serialize($service)]);
    }

    #[Route('/{id}', name: 'api_admin_services_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Service $service): JsonResponse
    {
        $service->setActive(false);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Prestation désactivée.']);
    }

    private function extractPayload(Request $request): array
    {
        if (str_contains((string) $request->headers->get('Content-Type'), 'application/json')) {
            return json_decode($request->getContent(), true) ?? [];
        }

        return $request->request->all();
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function serialize(Service $service): array
    {
        return [
            'id' => $service->getId(),
            'name' => $service->getName(),
            'price' => $service->getPrice(),
            'duration' => $service->getDuration(),
            'description' => $service->getDescription(),
            'picture' => $service->getPicture(),
            'active' => $service->isActive() !== false,
            'categoryId' => $service->getCategory()?->getId(),
            'category' => $service->getCategory()?->getName(),
        ];
    }
}
