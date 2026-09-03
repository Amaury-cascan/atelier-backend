<?php

namespace App\Controller\Api\Admin;

use App\Entity\PicturePresentation;
use App\Repository\PicturePresentationRepository;
use App\Service\ImageUploader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/pictures')]
class AdminPictureApiController extends AbstractController
{
    public function __construct(
        private readonly PicturePresentationRepository $pictureRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ImageUploader $imageUploader,
    ) {
    }

    #[Route('', name: 'api_admin_pictures_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $pictures = array_map(
            fn (PicturePresentation $picture) => $this->serialize($picture),
            $this->pictureRepository->findBy([], ['id' => 'DESC'])
        );

        return $this->json(['success' => true, 'pictures' => $pictures]);
    }

    #[Route('', name: 'api_admin_pictures_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $file = $request->files->get('image');
        if (!$file) {
            return $this->json(['success' => false, 'message' => 'Image obligatoire.'], Response::HTTP_BAD_REQUEST);
        }

        $picture = new PicturePresentation();
        $picture->setDescription(trim((string) $request->request->get('description', '')));
        $picture->setPicture($this->imageUploader->upload($file));

        $this->entityManager->persist($picture);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'picture' => $this->serialize($picture)], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_pictures_update', methods: ['POST', 'PUT', 'PATCH'], requirements: ['id' => '\d+'])]
    public function update(PicturePresentation $picture, Request $request): JsonResponse
    {
        if ($request->request->has('description') || str_contains((string) $request->headers->get('Content-Type'), 'json')) {
            $data = str_contains((string) $request->headers->get('Content-Type'), 'json')
                ? (json_decode($request->getContent(), true) ?? [])
                : $request->request->all();
            if (array_key_exists('description', $data)) {
                $picture->setDescription(trim((string) $data['description']));
            }
        }

        $file = $request->files->get('image');
        if ($file) {
            $picture->setPicture($this->imageUploader->upload($file));
        }

        $this->entityManager->flush();

        return $this->json(['success' => true, 'picture' => $this->serialize($picture)]);
    }

    #[Route('/{id}', name: 'api_admin_pictures_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(PicturePresentation $picture): JsonResponse
    {
        $this->entityManager->remove($picture);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Image supprimée.']);
    }

    private function serialize(PicturePresentation $picture): array
    {
        return [
            'id' => $picture->getId(),
            'description' => $picture->getDescription(),
            'picture' => $picture->getPicture(),
        ];
    }
}
