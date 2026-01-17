<?php

namespace App\Controller\Compte;

use App\Entity\Compte\UserMois;
use App\Form\Compte\UserMoisType;
use App\Repository\Compte\UserMoisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/compte/user/mois')]
final class UserMoisController extends AbstractController
{
    #[Route(name: 'app_compte_user_mois_index', methods: ['GET'])]
    public function index(UserMoisRepository $userMoisRepository): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException('Seuls les administrateurs peuvent accéder aux UserMois.');
        }

        $userMoisList = $userMoisRepository->findBy(['currentUser' => $user]);
        
        // Organiser les UserMois par Exercice, puis par année (décroissante)
        $exercicesByYear = [];
        foreach ($userMoisList as $userMoi) {
            $exercice = $userMoi->getExercice();
            if ($exercice !== null && $exercice->getMois() !== null) {
                $year = (int) $exercice->getMois()->format('Y');
                $month = (int) $exercice->getMois()->format('m');
                
                if (!isset($exercicesByYear[$year])) {
                    $exercicesByYear[$year] = [];
                }
                if (!isset($exercicesByYear[$year][$month])) {
                    $exercicesByYear[$year][$month] = ['exercice' => $exercice, 'userMois' => $userMoi];
                }
            }
        }
        
        // Trier les années en décroissant
        krsort($exercicesByYear);
        
        // Trier les mois dans chaque année en décroissant
        foreach ($exercicesByYear as $year => &$months) {
            krsort($months);
        }

        return $this->render('compte/user_mois/index.html.twig', [
            'exercicesByYear' => $exercicesByYear,
        ]);
    }

    #[Route('/{id}', name: 'app_compte_user_mois_show', methods: ['GET'])]
    public function show(UserMois $userMoi): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException('Seuls les administrateurs peuvent accéder aux UserMois.');
        }
        if ($userMoi->getCurrentUser() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce UserMois.');
        }

        return $this->render('compte/user_mois/show.html.twig', [
            'user_moi' => $userMoi,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_compte_user_mois_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, UserMois $userMoi, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException('Seuls les administrateurs peuvent modifier les UserMois.');
        }
        if ($userMoi->getCurrentUser() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce UserMois.');
        }

        // Vérifier si c'est une édition inline (données POST directes)
        if ($request->isMethod('POST') && ($request->request->has('salaire') || $request->request->has('tauxEnveloppe') || $request->request->has('epargne'))) {
            // Édition inline : données POST directes
            if ($this->isCsrfTokenValid('update_user_mois'.$userMoi->getId(), $request->request->get('_token'))) {
                $salaire = $request->request->get('salaire');
                $editMode = $request->request->get('editMode');
                
                if ($salaire !== null) {
                    $userMoi->setSalaire((int) $salaire);
                }
                
                // Si on modifie le taux, on utilise tauxEnveloppe et on calcule epargne
                if ($editMode === 'taux') {
                    $tauxEnveloppe = $request->request->get('tauxEnveloppe');
                    if ($tauxEnveloppe !== null && $tauxEnveloppe !== '') {
                        $userMoi->setTauxEnveloppe((float) $tauxEnveloppe);
                        // Calculer le montant de l'epargne : epargne = resteGlobal - montantEnveloppe
                        $resteGlobal = $this->calculateResteGlobal($userMoi);
                        $tauxEnveloppeValue = (float) $tauxEnveloppe;
                        $montantEnveloppe = ($resteGlobal * $tauxEnveloppeValue) / 100;
                        $epargneCalculee = $resteGlobal - $montantEnveloppe;
                        $userMoi->setEpargne((int) $epargneCalculee);
                    }
                }
                // Si on modifie l'epargne, on utilise epargne et on met tauxEnveloppe à null
                elseif ($editMode === 'epargne') {
                    $epargne = $request->request->get('epargne');
                    if ($epargne !== null && $epargne !== '') {
                        $userMoi->setEpargne((int) $epargne);
                        $userMoi->setTauxEnveloppe(null); // Réinitialiser tauxEnveloppe quand on modifie l'epargne
                    }
                }
                
                // S'assurer que l'utilisateur reste le même
                $userMoi->setCurrentUser($user);
                
                $entityManager->flush();
            }

            return $this->redirectToRoute('app_compte_user_mois_show', ['id' => $userMoi->getId()], Response::HTTP_SEE_OTHER);
        }
        
        // Formulaire Symfony classique
        $form = $this->createForm(UserMoisType::class, $userMoi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // S'assurer que l'utilisateur reste le même
            $userMoi->setCurrentUser($user);
            
            $entityManager->flush();

            return $this->redirectToRoute('app_compte_user_mois_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('compte/user_mois/edit.html.twig', [
            'user_moi' => $userMoi,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_compte_user_mois_delete', methods: ['POST'])]
    public function delete(Request $request, UserMois $userMoi, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user || !in_array('ROLE_ADMIN', $user->getRoles())) {
            throw $this->createAccessDeniedException('Seuls les administrateurs peuvent supprimer les UserMois.');
        }
        if ($userMoi->getCurrentUser() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à ce UserMois.');
        }

        if ($this->isCsrfTokenValid('delete'.$userMoi->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($userMoi);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_compte_user_mois_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Calcule le resteGlobal pour un UserMois
     */
    private function calculateResteGlobal(UserMois $userMoi): float
    {
        $salaire = $userMoi->getSalaire() ?? 0;
        $exercice = $userMoi->getExercice();
        
        if ($exercice && $exercice->getUserMois()->count() > 0) {
            $nombreUsers = $exercice->getUserMois()->count();
            $baseParPersonne = (($exercice->getMontantTotal() ?? 0) - ($exercice->getMontantAide() ?? 0)) / $nombreUsers;
            
            // Trouver l'index de userMois dans exercice.userMois
            $userMoisIndex = -1;
            $userMoisList = $exercice->getUserMois()->toArray();
            foreach ($userMoisList as $index => $um) {
                if ($um->getId() === $userMoi->getId()) {
                    $userMoisIndex = $index;
                    break;
                }
            }
            
            if ($userMoisIndex >= 0) {
                $totalAjuste = $baseParPersonne;
                
                // Soustraire ses propres dépenses communes (divisées par le nombre d'users)
                $totalDepensesCommunesUser = 0;
                foreach ($userMoi->getUserDepenseFixes() as $depenseFixe) {
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
                foreach ($userMoi->getUserDepenseFixes() as $depenseFixe) {
                    $totalDepensesFixes += $depenseFixe->getMontant() ?? 0;
                }
                
                // Calculer le reste : Salaire - Dépenses compte commun - Toutes les dépenses fixes
                $resteGlobal = $salaire - $totalAjuste - $totalDepensesFixes;
                return $resteGlobal;
            }
        } else {
            // Si pas d'exercice, le reste est juste le salaire moins les dépenses fixes
            $totalDepensesFixes = 0;
            foreach ($userMoi->getUserDepenseFixes() as $depenseFixe) {
                $totalDepensesFixes += $depenseFixe->getMontant() ?? 0;
            }
            return $salaire - $totalDepensesFixes;
        }
        
        return 0.0;
    }
}
