<?php

namespace App\Controller;

use App\Entity\PicturePresentation;
use App\Form\PicturePresentationType;
use App\Repository\PicturePresentationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/picture/presentation')]
final class PicturePresentationController extends AbstractController
{
    #[Route(name: 'app_picture_presentation_index', methods: ['GET'])]
    public function index(PicturePresentationRepository $picturePresentationRepository): Response
    {
        return $this->render('picture_presentation/index.html.twig', [
            'picture_presentations' => $picturePresentationRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_picture_presentation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $picturePresentation = new PicturePresentation();
        $form = $this->createForm(PicturePresentationType::class, $picturePresentation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Récupérer le fichier image
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                // Créer un nom unique pour l'image
                $fileName = uniqid() . '.' . $imageFile->guessExtension();

                // Déplacer le fichier vers le répertoire de destination
                try {
                    $imageFile->move(
                        $this->getParameter('images_service_directory'), // Chemin de destination
                        $fileName
                    );
                } catch (FileException $e) {
                    // Gérer l'exception si quelque chose ne va pas lors de l'upload
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : ' . $e->getMessage());
                }

                // Enregistrer le nom de l'image dans l'entité
                $picturePresentation->setPicture($fileName);
            }
            $entityManager->persist($picturePresentation);
            $entityManager->flush();

            return $this->redirectToRoute('app_picture_presentation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('picture_presentation/new.html.twig', [
            'picture_presentation' => $picturePresentation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_picture_presentation_show', methods: ['GET'])]
    public function show(PicturePresentation $picturePresentation): Response
    {
        return $this->render('picture_presentation/show.html.twig', [
            'picture_presentation' => $picturePresentation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_picture_presentation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, PicturePresentation $picturePresentation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PicturePresentationType::class, $picturePresentation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_picture_presentation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('picture_presentation/edit.html.twig', [
            'picture_presentation' => $picturePresentation,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_picture_presentation_delete', methods: ['POST'])]
    public function delete(Request $request, PicturePresentation $picturePresentation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$picturePresentation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($picturePresentation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_picture_presentation_index', [], Response::HTTP_SEE_OTHER);
    }
}
