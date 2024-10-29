<?php

namespace App\Controller\Api;

use App\Entity\Category;
use App\Repository\CategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/categories')]
class CategoryApiController extends AbstractController
{
    #[Route('/', name: 'api_category_index', methods: ['GET'])]
    public function index(CategoryRepository $categoryRepository): JsonResponse
    {
        // Récupérer toutes les catégories
        $categories = $categoryRepository->findAll();

        // Transformer les objets Category en tableau associatif pour le JSON
        $data = [];
        foreach ($categories as $category) {
            $data[] = [
                'id' => $category->getId(),
                'services' => $category->getServices(),
                'name' => $category->getName(),
                'picture' => $category->getPicture(),
                'description' => $category->getDescription(),
            ];
        }

        // Retourner les données en JSON
        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }

    #[Route('/{id}', name: 'api_category_show', methods: ['GET'])]
    public function show(Category $category): JsonResponse
    {
        // Transformer l'objet Category en tableau associatif pour le JSON
        $data = [
            'id' => $category->getId(),
            'services' => $category->getServices(),
            'name' => $category->getName(),
            'picture' => $category->getPicture(),
            'description' => $category->getDescription(),
        ];

        // Retourner les données en JSON
        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }
}
