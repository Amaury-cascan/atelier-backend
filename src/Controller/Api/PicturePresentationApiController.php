<?php

namespace App\Controller\Api;

use App\Entity\PicturePresentation;
use App\Repository\PicturePresentationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/picture-presentation')]
class PicturePresentationApiController extends AbstractController
{
    private PicturePresentationRepository $picturePresentationRepositoryRepository;

    public function __construct(PicturePresentationRepository $picturePresentationRepositoryRepository)
    {
        $this->picturePresentationRepositoryRepository = $picturePresentationRepositoryRepository;
    }

    #[Route('/', name: 'api_picture_presentation_index', methods: ['GET'])]
    public function findAll(): JsonResponse
    {
        $pictures = $this->picturePresentationRepositoryRepository->findAll();

        // Convertit les services en tableau associatif
        $data = [];
        foreach ($pictures as $picture) {
            $data[] = [
                'id' => $picture->getId(),
                'description' => $picture->getDescription(),
                'picture' => $picture->getPicture(),
            ];
        }

        return new JsonResponse($data, JsonResponse::HTTP_OK);
    }
}
