<?php

namespace App\Controller\Compte;

use App\Entity\Compte\DepenseFixe;
use App\Entity\Compte\Exercice;
use App\Form\Compte\DepenseFixeType;
use App\Repository\Compte\DepenseFixeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/compte/depense/fixe')]
final class DepenseFixeController extends AbstractController
{
    
    #[Route('/{id}/new', name: 'app_compte_depense_fixe_new', methods: ['POST'])]
    public function newFromExercice(Request $request, Exercice $exercice, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('depense_fixe_new', $request->request->get('_token'))) {
            $nom = $request->request->get('nom');
            $montant = $request->request->get('montant');
            
            if ($nom && $montant !== null) {
                $depenseFixe = new DepenseFixe();
                $depenseFixe->setNom($nom);
                $depenseFixe->setMontant((int) $montant);
                $depenseFixe->setExercice($exercice);
                
                // Ajouter le montant au montantTotal de l'exercice
                $montantTotal = $exercice->getMontantTotal() ?? 0;
                $exercice->setMontantTotal($montantTotal + (int) $montant);
                
                $entityManager->persist($depenseFixe);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_compte_exercice_show', ['id' => $exercice->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_compte_depense_fixe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, DepenseFixe $depenseFixe, EntityManagerInterface $entityManager): Response
    {
        // Stocker l'ancien montant avant le traitement du formulaire
        $ancienMontant = $depenseFixe->getMontant() ?? 0;
        $exercice = $depenseFixe->getExercice();
        $ancienMontantTotal = $exercice?->getMontantTotal() ?? 0;
        
        // Vérifier si c'est une édition inline (données POST directes)
        if ($request->isMethod('POST') && $request->request->has('nom') && $request->request->has('montant')) {
            // Édition inline : données POST directes
            $nom = $request->request->get('nom');
            $montant = (float) $request->request->get('montant');
            
            $depenseFixe->setNom($nom);
            $depenseFixe->setMontant($montant);
            
            // En mode update : ajuster montantTotal = ancien montantTotal - ancien montant + nouveau montant
            if ($exercice !== null) {
                $nouveauMontantTotal = $ancienMontantTotal - $ancienMontant + $montant;
                $exercice->setMontantTotal($nouveauMontantTotal);
            }
            
            $entityManager->flush();

            // Rediriger vers la page de l'exercice si fournie, sinon vers l'index
            $exerciceId = $request->request->get('exerciceId') ?? $request->query->get('exerciceId');
            if ($exerciceId) {
                return $this->redirectToRoute('app_compte_exercice_show', ['id' => $exerciceId], Response::HTTP_SEE_OTHER);
            }
            return $this->redirectToRoute('app_compte_depense_fixe_index', [], Response::HTTP_SEE_OTHER);
        }
        
        // Formulaire Symfony classique
        $form = $this->createForm(DepenseFixeType::class, $depenseFixe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // En mode update : ajuster montantTotal = ancien montantTotal - ancien montant + nouveau montant
            $exercice = $depenseFixe->getExercice();
            if ($exercice !== null) {
                $nouveauMontant = $depenseFixe->getMontant() ?? 0;
                $nouveauMontantTotal = $ancienMontantTotal - $ancienMontant + $nouveauMontant;
                $exercice->setMontantTotal($nouveauMontantTotal);
            }
            
            $entityManager->flush();

            // Rediriger vers la page de l'exercice si fournie, sinon vers l'index
            $exerciceId = $request->request->get('exerciceId') ?? $request->query->get('exerciceId');
            if ($exerciceId) {
                return $this->redirectToRoute('app_compte_exercice_show', ['id' => $exerciceId], Response::HTTP_SEE_OTHER);
            }
            return $this->redirectToRoute('app_compte_depense_fixe_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('compte/depense_fixe/edit.html.twig', [
            'depense_fixe' => $depenseFixe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_compte_depense_fixe_delete', methods: ['POST'])]
    public function delete(Request $request, DepenseFixe $depenseFixe, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$depenseFixe->getId(), $request->getPayload()->getString('_token'))) {
            // Soustraire le montant du montantTotal de l'exercice
            $exercice = $depenseFixe->getExercice();
            if ($exercice !== null) {
                $montant = $depenseFixe->getMontant() ?? 0;
                $montantTotal = $exercice->getMontantTotal() ?? 0;
                $exercice->setMontantTotal($montantTotal - $montant);
            }
            
            $entityManager->remove($depenseFixe);
            $entityManager->flush();
        }

        // Rediriger vers la page de l'exercice si fournie, sinon vers l'index
        $exerciceId = $request->request->get('exerciceId') ?? $request->query->get('exerciceId') ?? $depenseFixe->getExercice()?->getId();
        if ($exerciceId) {
            return $this->redirectToRoute('app_compte_exercice_show', ['id' => $exerciceId], Response::HTTP_SEE_OTHER);
        }
        return $this->redirectToRoute('app_compte_depense_fixe_index', [], Response::HTTP_SEE_OTHER);
    }
}
