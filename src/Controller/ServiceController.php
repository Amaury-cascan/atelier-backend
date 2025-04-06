<?php

namespace App\Controller;

use App\Entity\Service;
use App\Form\ServiceType;
use App\Repository\ServiceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/administration/prestation')]
class ServiceController extends AbstractController
{
    #[Route('/', name: 'app_service_index', methods: ['GET'])]
    public function index(ServiceRepository $serviceRepository): Response
    {
        return $this->render('service/index.html.twig', [
            'services' => $serviceRepository->findAll(),
        ]);
    }

    #[Route('/nouvelle', name: 'app_service_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $service = new Service();
        $form = $this->createForm(ServiceType::class, $service);
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
                $service->setPicture($fileName);
            }

            // Enregistrer le service dans la base de données
            $entityManager->persist($service);
            $entityManager->flush();

            return $this->redirectToRoute('app_service_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('service/new.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_service_show', methods: ['GET'])]
    public function show(Service $service): Response
    {
        return $this->render('service/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/{id}/editer', name: 'app_service_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ServiceType::class, $service);
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
                    // Enregistrer le nom de l'image dans l'entité
                    $service->setPicture($fileName);
                } catch (FileException $e) {
                    // Gérer l'exception si quelque chose ne va pas lors de l'upload
                    $this->addFlash('error', 'Erreur lors de l\'upload de l\'image : ' . $e->getMessage());
                }
            }

            // Enregistrer les modifications dans la base de données
            $entityManager->flush();

            return $this->redirectToRoute('app_service_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('service/edit.html.twig', [
            'service' => $service,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_service_delete', methods: ['POST'])]
    public function delete(Request $request, Service $service, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->request->get('_token'))) {
            $service->setActive(false);
            $entityManager->persist($service);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_service_index', [], Response::HTTP_SEE_OTHER);
    }
}
