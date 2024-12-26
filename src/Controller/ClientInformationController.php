<?php

namespace App\Controller;

use App\Entity\Client;
use App\Entity\ClientInformation;
use App\Form\ClientInformationType;
use App\Repository\ClientInformationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('administration/client/information')]
final class ClientInformationController extends AbstractController
{
    #[Route(name: 'app_client_information_index', methods: ['GET'])]
    public function index(ClientInformationRepository $clientInformationRepository): Response
    {
        return $this->render('client_information/index.html.twig', [
            'client_informations' => $clientInformationRepository->findAll(),
        ]);
    }

// src/Controller/ClientInformationController.php

    #[Route('/new', name: 'app_client_information_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, Client $client): Response
    {
        $clientInformation = new ClientInformation();
        $form = $this->createForm(ClientInformationType::class, $clientInformation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Lier l'information au client
            $clientInformation->setClient($client); // Assurez-vous d'avoir cette méthode dans ClientInformation
            $entityManager->persist($clientInformation);
            $entityManager->flush();

            // Redirection vers la page de détails du client
            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()], Response::HTTP_SEE_OTHER);
        }

        // Ce bloc de code ne sera plus utilisé si nous avons le formulaire intégré dans le show
        return $this->render('client_information/new.html.twig', [
            'client_information' => $clientInformation,
            'form' => $form,
        ]);
    }


    #[Route('/{id}', name: 'app_client_information_show', methods: ['GET'])]
    public function show(ClientInformation $clientInformation): Response
    {
        return $this->render('client_information/show.html.twig', [
            'client_information' => $clientInformation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_client_information_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, ClientInformation $clientInformation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ClientInformationType::class, $clientInformation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_client_show', ['id' => $clientInformation->getClient()->getId()]);
        }

        return $this->render('client_information/edit.html.twig', [
            'client_information' => $clientInformation,
            'form' => $form,
            'client' => $clientInformation->getClient(),
        ]);
    }

    #[Route('/{id}', name: 'app_client_information_delete', methods: ['POST'])]
    public function delete(Request $request, ClientInformation $clientInformation, EntityManagerInterface $entityManager): Response
    {
        // Vérifiez le token CSRF pour la sécurité
        if ($this->isCsrfTokenValid('delete'.$clientInformation->getId(), $request->request->get('_token'))) {
            // Récupérez les images associées
            $pictures = $clientInformation->getPictures(); // Méthode de relation OneToMany dans votre entité ClientInformation

            // Supprimez chaque image
            foreach ($pictures as $picture) {
                // Supprimez le fichier physique si nécessaire
                $imagePath = $this->getParameter('images_client_directory').'/'.$picture->getName();
                if (file_exists($imagePath)) {
                    unlink($imagePath); // Supprime le fichier physique
                }

                $entityManager->remove($picture); // Supprime l'entité Picture
            }

            // Supprimez l'entité ClientInformation
            $client = $clientInformation->getClient();
            $entityManager->remove($clientInformation);
            $entityManager->flush();

            // Redirigez après la suppression
            return $this->redirectToRoute('app_client_show', ['id' => $client->getId()], Response::HTTP_SEE_OTHER);
        }

        // Redirection en cas de token invalide
        return $this->redirectToRoute('app_client_index');
    }
}
