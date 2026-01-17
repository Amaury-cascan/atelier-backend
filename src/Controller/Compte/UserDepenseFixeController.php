<?php

namespace App\Controller\Compte;

use App\Entity\Compte\UserDepenseFixe;
use App\Entity\Compte\UserMois;
use App\Form\Compte\UserDepenseFixeType;
use App\Repository\Compte\UserDepenseFixeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/compte/user/depense/fixe')]
final class UserDepenseFixeController extends AbstractController
{

    #[Route('/{id}/edit', name: 'app_compte_user_depense_fixe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, UserDepenseFixe $userDepenseFixe, EntityManagerInterface $entityManager): Response
    {
        $userMoisId = $userDepenseFixe->getUserMois()?->getId();
        
        // Check if it's an inline edit (direct POST data)
        if ($request->isMethod('POST') && $request->request->has('nom') && $request->request->has('montant')) {
            if ($this->isCsrfTokenValid('edit_user_depense_fixe'.$userDepenseFixe->getId(), $request->request->get('_token'))) {
                $nom = $request->request->get('nom');
                $montant = (int) $request->request->get('montant');
                $isDepenseCommune = $request->request->get('isDepenseCommune') === '1';
                
                $userDepenseFixe->setNom($nom);
                $userDepenseFixe->setMontant($montant);
                $userDepenseFixe->setIsDepenseCommune($isDepenseCommune);
                
                $entityManager->flush();
            }
            return $this->redirectToRoute('app_compte_user_mois_show', ['id' => $userMoisId], Response::HTTP_SEE_OTHER);
        }
        
        // Classic Symfony form
        $form = $this->createForm(UserDepenseFixeType::class, $userDepenseFixe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_compte_user_mois_show', ['id' => $userMoisId], Response::HTTP_SEE_OTHER);
        }

        return $this->render('compte/user_depense_fixe/edit.html.twig', [
            'user_depense_fixe' => $userDepenseFixe,
            'form' => $form,
        ]);
    }

    #[Route('/user-mois/{userMoisId}/new', name: 'app_compte_user_depense_fixe_new_from_user_mois', methods: ['POST'])]
    public function newFromUserMois(Request $request, int $userMoisId, EntityManagerInterface $entityManager): Response
    {
        $userMois = $entityManager->getRepository(UserMois::class)->find($userMoisId);
        if (!$userMois) {
            throw $this->createNotFoundException('UserMois non trouvé.');
        }

        if ($this->isCsrfTokenValid('user_depense_fixe_new_from_user_mois'.$userMoisId, $request->request->get('_token'))) {
            $nom = $request->request->get('nom');
            $montant = $request->request->get('montant');
            $isDepenseCommune = $request->request->get('isDepenseCommune') === '1';
            
            if ($nom && $montant !== null) {
                $userDepenseFixe = new UserDepenseFixe();
                $userDepenseFixe->setNom($nom);
                $userDepenseFixe->setMontant((int) $montant);
                $userDepenseFixe->setIsDepenseCommune($isDepenseCommune);
                $userDepenseFixe->setUserMois($userMois);
                
                $entityManager->persist($userDepenseFixe);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_compte_user_mois_show', ['id' => $userMoisId], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}', name: 'app_compte_user_depense_fixe_delete', methods: ['POST'])]
    public function delete(Request $request, UserDepenseFixe $userDepenseFixe, EntityManagerInterface $entityManager): Response
    {
        $userMoisId = $userDepenseFixe->getUserMois()?->getId();
        
        if ($this->isCsrfTokenValid('delete'.$userDepenseFixe->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($userDepenseFixe);
            $entityManager->flush();
        }

        if ($userMoisId) {
            return $this->redirectToRoute('app_compte_user_mois_show', ['id' => $userMoisId], Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_compte_user_depense_fixe_index', [], Response::HTTP_SEE_OTHER);
    }
}
