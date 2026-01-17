<?php

namespace App\Controller\Compte;

use App\Entity\Compte\Enveloppe;
use App\Form\Compte\EnveloppeType;
use App\Repository\Compte\EnveloppeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/compte/enveloppe')]
final class EnveloppeController extends AbstractController
{

    #[Route('/user-mois/{userMoisId}/new', name: 'app_compte_enveloppe_new_from_user_mois', methods: ['POST'])]
    public function newFromUserMois(Request $request, int $userMoisId, EntityManagerInterface $entityManager): Response
    {
        $userMois = $entityManager->getRepository(\App\Entity\Compte\UserMois::class)->find($userMoisId);
        if (!$userMois) {
            throw $this->createNotFoundException('UserMois non trouvé.');
        }

        if ($this->isCsrfTokenValid('enveloppe_new_from_user_mois'.$userMoisId, $request->request->get('_token'))) {
            $nom = $request->request->get('nom');
            $editMode = $request->request->get('editMode');
            
            if ($nom) {
                $enveloppe = new Enveloppe();
                $enveloppe->setNom($nom);
                $enveloppe->setUserMois($userMois);
                
                // Calculer le resteGlobal et le montantEnveloppe
                $montantEnveloppe = $this->calculateMontantEnveloppe($userMois);
                
                if ($editMode === 'taux') {
                    $pourcentage = (float) $request->request->get('pourcentage');
                    $enveloppe->setPourcentage((int) $pourcentage);
                    $montant = ($montantEnveloppe * $pourcentage) / 100;
                    $enveloppe->setMontant((int) $montant);
                } else {
                    $montant = (int) $request->request->get('montant');
                    $enveloppe->setMontant($montant);
                    if ($montantEnveloppe > 0) {
                        $pourcentage = ($montant / $montantEnveloppe) * 100;
                        $enveloppe->setPourcentage((int) $pourcentage);
                    } else {
                        $enveloppe->setPourcentage(0);
                    }
                }
                
                $entityManager->persist($enveloppe);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_compte_user_mois_show', ['id' => $userMoisId], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/edit', name: 'app_compte_enveloppe_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Enveloppe $enveloppe, EntityManagerInterface $entityManager): Response
    {
        $userMoisId = $enveloppe->getUserMois()?->getId();
        
        // Check if it's an inline edit (direct POST data)
        if ($request->isMethod('POST') && $request->request->has('nom')) {
            if ($this->isCsrfTokenValid('edit_enveloppe'.$enveloppe->getId(), $request->request->get('_token'))) {
                $nom = $request->request->get('nom');
                $enveloppe->setNom($nom);
                
                // Calculer le resteGlobal et le montantEnveloppe
                $userMois = $enveloppe->getUserMois();
                $montantEnveloppe = $this->calculateMontantEnveloppe($userMois);
                
                $pourcentage = $request->request->get('pourcentage');
                $montant = $request->request->get('montant');
                
                // Récupérer les anciennes valeurs pour détecter lequel a changé
                $ancienPourcentage = $enveloppe->getPourcentage();
                $ancienMontant = $enveloppe->getMontant();
                
                // Vérifier lequel a été modifié en comparant avec les anciennes valeurs
                $pourcentageModifie = false;
                $montantModifie = false;
                
                if ($pourcentage !== null && $pourcentage !== '') {
                    $nouveauPourcentage = (float) $pourcentage;
                    if ($nouveauPourcentage != $ancienPourcentage) {
                        $pourcentageModifie = true;
                    }
                }
                
                if ($montant !== null && $montant !== '') {
                    $nouveauMontant = (int) $montant;
                    if ($nouveauMontant != $ancienMontant) {
                        $montantModifie = true;
                    }
                }
                
                // Si le taux a été modifié, on l'utilise comme source de vérité
                if ($pourcentageModifie) {
                    $pourcentage = (float) $request->request->get('pourcentage');
                    $enveloppe->setPourcentage((int) $pourcentage);
                    $montant = ($montantEnveloppe * $pourcentage) / 100;
                    $enveloppe->setMontant((int) $montant);
                } 
                // Si le montant a été modifié (et pas le taux), on l'utilise comme source de vérité
                elseif ($montantModifie) {
                    $montant = (int) $request->request->get('montant');
                    $enveloppe->setMontant($montant);
                    if ($montantEnveloppe > 0) {
                        $pourcentage = ($montant / $montantEnveloppe) * 100;
                        $enveloppe->setPourcentage((int) $pourcentage);
                    } else {
                        $enveloppe->setPourcentage(0);
                    }
                }
                // Si aucun n'a été modifié mais qu'ils sont fournis, on priorise le taux
                elseif ($pourcentage !== null && $pourcentage !== '') {
                    $pourcentage = (float) $pourcentage;
                    $enveloppe->setPourcentage((int) $pourcentage);
                    $montant = ($montantEnveloppe * $pourcentage) / 100;
                    $enveloppe->setMontant((int) $montant);
                } elseif ($montant !== null && $montant !== '') {
                    $montant = (int) $montant;
                    $enveloppe->setMontant($montant);
                    if ($montantEnveloppe > 0) {
                        $pourcentage = ($montant / $montantEnveloppe) * 100;
                        $enveloppe->setPourcentage((int) $pourcentage);
                    } else {
                        $enveloppe->setPourcentage(0);
                    }
                }
                
                $entityManager->flush();
            }
            return $this->redirectToRoute('app_compte_user_mois_show', ['id' => $userMoisId], Response::HTTP_SEE_OTHER);
        }
        
        // Classic Symfony form
        $form = $this->createForm(EnveloppeType::class, $enveloppe);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_compte_user_mois_show', ['id' => $userMoisId], Response::HTTP_SEE_OTHER);
        }

        return $this->render('compte/enveloppe/edit.html.twig', [
            'enveloppe' => $enveloppe,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_compte_enveloppe_delete', methods: ['POST'])]
    public function delete(Request $request, Enveloppe $enveloppe, EntityManagerInterface $entityManager): Response
    {
        $userMoisId = $enveloppe->getUserMois()?->getId();
        
        if ($this->isCsrfTokenValid('delete'.$enveloppe->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($enveloppe);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_compte_user_mois_show', ['id' => $userMoisId], Response::HTTP_SEE_OTHER);
    }

    private function calculateMontantEnveloppe(\App\Entity\Compte\UserMois $userMois): float
    {
        $salaire = $userMois->getSalaire() ?? 0;
        $tauxEnveloppe = $userMois->getTauxEnveloppe() ?? 0;
        
        // Calculer le resteGlobal
        $resteGlobal = 0;
        $exercice = $userMois->getExercice();
        
        if ($exercice && $exercice->getUserMois()->count() > 0) {
            $nombreUsers = $exercice->getUserMois()->count();
            $baseParPersonne = (($exercice->getMontantTotal() ?? 0) - ($exercice->getMontantAide() ?? 0)) / $nombreUsers;
            
            // Trouver l'index de userMois dans exercice.userMois
            $userMoisIndex = -1;
            $userMoisList = $exercice->getUserMois()->toArray();
            foreach ($userMoisList as $index => $um) {
                if ($um->getId() === $userMois->getId()) {
                    $userMoisIndex = $index;
                    break;
                }
            }
            
            if ($userMoisIndex >= 0) {
                $totalAjuste = $baseParPersonne;
                
                // Soustraire ses propres dépenses communes (divisées par le nombre d'users)
                $totalDepensesCommunesUser = 0;
                foreach ($userMois->getUserDepenseFixes() as $depenseFixe) {
                    if ($depenseFixe->isDepenseCommune()) {
                        $totalDepensesCommunesUser += $depenseFixe->getMontant() ?? 0;
                    }
                }
                $totalAjuste -= ($totalDepensesCommunesUser / $nombreUsers);
                
                // Calculer les montants ajoutés par les autres utilisateurs
                foreach ($userMoisList as $autreIndex => $autreUserMois) {
                    if ($autreIndex !== $userMoisIndex) {
                        $autresDepensesCommunes = 0;
                        foreach ($autreUserMois->getUserDepenseFixes() as $depenseFixe) {
                            if ($depenseFixe->isDepenseCommune()) {
                                $autresDepensesCommunes += $depenseFixe->getMontant() ?? 0;
                            }
                        }
                        $totalAjuste += ($autresDepensesCommunes / $nombreUsers);
                    }
                }
                
                // Calculer le total de toutes les dépenses fixes (normales + communes)
                $totalDepensesFixes = 0;
                foreach ($userMois->getUserDepenseFixes() as $depenseFixe) {
                    $totalDepensesFixes += $depenseFixe->getMontant() ?? 0;
                }
                
                // Calculer le reste : Salaire - Dépenses compte commun - Toutes les dépenses fixes
                $resteGlobal = $salaire - $totalAjuste - $totalDepensesFixes;
            }
        } else {
            // Si pas d'exercice, le reste est juste le salaire moins les dépenses fixes
            $totalDepensesFixes = 0;
            foreach ($userMois->getUserDepenseFixes() as $depenseFixe) {
                $totalDepensesFixes += $depenseFixe->getMontant() ?? 0;
            }
            $resteGlobal = $salaire - $totalDepensesFixes;
        }
        
        // Calculer le montantEnveloppe : (resteGlobal * tauxEnveloppe) / 100
        return ($resteGlobal * $tauxEnveloppe) / 100;
    }
}
