<?php

namespace App\Controller\Api\Admin;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/categories')]
class AdminCategoryApiController extends AbstractController
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageUploader $imageUploader,
    ) {
    }

    #[Route('', name: 'api_admin_categories_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $categories = array_map(fn (Category $category) => $this->serialize($category), $this->categoryRepository->findBy([], ['name' => 'ASC']));

        return $this->json(['success' => true, 'categories' => $categories]);
    }

    #[Route('/{id}', name: 'api_admin_categories_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Category $category): JsonResponse
    {
        return $this->json(['success' => true, 'category' => $this->serialize($category)]);
    }

    #[Route('', name: 'api_admin_categories_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->extractPayload($request);
        $file = $request->files->get('image');

        if (empty($data['name']) || empty($data['description']) || !$file) {
            return $this->json(['success' => false, 'message' => 'Nom, description et image sont obligatoires.'], Response::HTTP_BAD_REQUEST);
        }

        $category = new Category();
        $category->setName(trim((string) $data['name']));
        $category->setDescription(trim((string) $data['description']));
        $category->setPicture($this->imageUploader->upload($file));

        $this->entityManager->persist($category);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'category' => $this->serialize($category)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_categories_update', methods: ['POST', 'PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(Category $category, Request $request): JsonResponse
    {
        $data = $this->extractPayload($request);

        if (isset($data['name'])) {
            $category->setName(trim((string) $data['name']));
        }
        if (isset($data['description'])) {
            $category->setDescription(trim((string) $data['description']));
        }

        $file = $request->files->get('image');
        if ($file) {
            $category->setPicture($this->imageUploader->upload($file));
        }

        $this->entityManager->flush();

        return $this->json(['success' => true, 'category' => $this->serialize($category)]);
    }

    #[Route('/{id}', name: 'api_admin_categories_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Category $category): JsonResponse
    {
        if ($category->getServices()->count() > 0) {
            return $this->json([
                'success' => false,
                'message' => 'Impossible de supprimer une catégorie qui contient encore des prestations.',
            ], Response::HTTP_CONFLICT);
        }

        $this->entityManager->remove($category);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Catégorie supprimée.']);
    }

    private function extractPayload(Request $request): array
    {
        if (str_contains((string) $request->headers->get('Content-Type'), 'application/json')) {
            return json_decode($request->getContent(), true) ?? [];
        }

        return $request->request->all();
    }

    private function serialize(Category $category): array
    {
        return [
            'id' => $category->getId(),
            'name' => $category->getName(),
            'description' => $category->getDescription(),
            'picture' => $category->getPicture(),
            'servicesCount' => $category->getServices()->count(),
        ];
    }
}
