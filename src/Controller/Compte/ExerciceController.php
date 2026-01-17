<?php

namespace App\Controller\Compte;

use App\Entity\Compte\Exercice;
use App\Entity\Compte\UserMois;
use App\Form\Compte\ExerciceType;
use App\Repository\Compte\ExerciceRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/compte/exercice')]
final class ExerciceController extends AbstractController
{
    #[Route(name: 'app_compte_exercice_index', methods: ['GET'])]
    public function index(ExerciceRepository $exerciceRepository): Response
    {
        $exercices = $exerciceRepository->findAll();
        
        // Organiser les exercices par année (décroissante)
        $exercicesByYear = [];
        foreach ($exercices as $exercice) {
            if ($exercice->getMois() !== null) {
                $year = (int) $exercice->getMois()->format('Y');
                $month = (int) $exercice->getMois()->format('m');
                
                if (!isset($exercicesByYear[$year])) {
                    $exercicesByYear[$year] = [];
                }
                $exercicesByYear[$year][$month] = $exercice;
            }
        }
        
        // Trier les années en décroissant
        krsort($exercicesByYear);
        
        // Trier les mois dans chaque année en décroissant
        foreach ($exercicesByYear as $year => &$months) {
            krsort($months);
        }
        
        return $this->render('compte/exercice/index.html.twig', [
            'exercicesByYear' => $exercicesByYear,
        ]);
    }

    #[Route('/new', name: 'app_compte_exercice_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ExerciceRepository $exerciceRepository, UserRepository $userRepository): Response
    {
        $exercice = new Exercice();
        
        // Trouver le dernier exercice
        $lastExercice = $exerciceRepository->findLastExercice();
        
        // Si aucun exercice n'existe, afficher le formulaire pour choisir le premier mois
        if ($lastExercice === null || $lastExercice->getMois() === null) {
            $form = $this->createForm(ExerciceType::class, $exercice);
            $form->handleRequest($request);

            if ($form->isSubmitted()) {
                // Vérifier si un exercice avec le même mois/année existe déjà
                if ($exercice->getMois() !== null) {
                    $mois = $exercice->getMois();
                    $year = (int) $mois->format('Y');
                    $month = (int) $mois->format('m');
                    
                    $existingExercice = $exerciceRepository->findOneByMonthAndYear($year, $month);
                    
                    if ($existingExercice !== null) {
                        $form->get('mois')->addError(new FormError('Un exercice pour ce mois et cette année existe déjà.'));
                    }
                }
                
                if ($form->isValid()) {
                    $entityManager->persist($exercice);
                    $entityManager->flush();

                    // Créer un UserMois pour chaque admin et copier toutes les données
                    $this->createUserMoisForAdmins($exercice, $entityManager, $userRepository, $lastExercice);

                    return $this->redirectToRoute('app_compte_exercice_index', [], Response::HTTP_SEE_OTHER);
                }
            }

            return $this->render('compte/exercice/new.html.twig', [
                'exercice' => $exercice,
                'form' => $form,
            ]);
        }
        
        // Si des exercices existent, créer directement le mois suivant
        $lastMois = $lastExercice->getMois();
        // Créer un nouveau DateTime à partir du dernier mois et ajouter 1 mois
        $nextMois = new \DateTime($lastMois->format('Y-m-01'));
        $nextMois->modify('+1 month');
        
        // Vérifier si un exercice avec le même mois/année existe déjà
        $year = (int) $nextMois->format('Y');
        $month = (int) $nextMois->format('m');
        
        $existingExercice = $exerciceRepository->findOneByMonthAndYear($year, $month);
        
        if ($existingExercice === null) {
            $exercice->setMois($nextMois);
            $entityManager->persist($exercice);
            $entityManager->flush();

            // Créer un UserMois pour chaque admin et copier toutes les données
            $this->createUserMoisForAdmins($exercice, $entityManager, $userRepository, $lastExercice);
        }

        return $this->redirectToRoute('app_compte_exercice_index', [], Response::HTTP_SEE_OTHER);
    }

    /**
     * Crée un UserMois pour chaque utilisateur avec le rôle ROLE_ADMIN et copie toutes les données de l'exercice précédent
     */
    private function createUserMoisForAdmins(Exercice $exercice, EntityManagerInterface $entityManager, UserRepository $userRepository, ?Exercice $lastExercice = null): void
    {
        // Copier les données de l'exercice précédent vers le nouvel exercice
        if ($lastExercice !== null) {
            // Copier le montantAide
            $exercice->setMontantAide($lastExercice->getMontantAide());
            
            // Copier les DepenseFixe
            foreach ($lastExercice->getDepenseFixes() as $depenseFixe) {
                $newDepenseFixe = new \App\Entity\Compte\DepenseFixe();
                $newDepenseFixe->setNom($depenseFixe->getNom());
                $newDepenseFixe->setMontant($depenseFixe->getMontant());
                $newDepenseFixe->setExercice($exercice);
                $entityManager->persist($newDepenseFixe);
                
                // Mettre à jour le montantTotal de l'exercice
                $montantTotal = $exercice->getMontantTotal() ?? 0;
                $exercice->setMontantTotal($montantTotal + $depenseFixe->getMontant());
            }
        }
        
        // Récupérer tous les utilisateurs
        $allUsers = $userRepository->findAll();
        
        // Filtrer les admins
        $adminUsers = array_filter($allUsers, function($user) {
            return in_array('ROLE_ADMIN', $user->getRoles());
        });
        
        // Créer un UserMois pour chaque admin et copier les données
        foreach ($adminUsers as $adminUser) {
            // Vérifier si un UserMois existe déjà pour cet admin et cet exercice
            $existingUserMois = null;
            foreach ($adminUser->getUserMois() as $userMoi) {
                if ($userMoi->getExercice() === $exercice) {
                    $existingUserMois = $userMoi;
                    break;
                }
            }
            
            // Créer seulement si aucun UserMois n'existe pour cet exercice
            if ($existingUserMois === null) {
                $userMoi = new UserMois();
                $userMoi->setCurrentUser($adminUser);
                $userMoi->setExercice($exercice);
                
                // Si un exercice précédent existe, copier les données du UserMois correspondant
                if ($lastExercice !== null) {
                    // Trouver le UserMois correspondant dans l'exercice précédent
                    $lastUserMois = null;
                    foreach ($lastExercice->getUserMois() as $um) {
                        if ($um->getCurrentUser() === $adminUser) {
                            $lastUserMois = $um;
                            break;
                        }
                    }
                    
                    // Si on a trouvé un UserMois précédent, copier ses données
                    if ($lastUserMois !== null) {
                        // Copier les champs du UserMois
                        $userMoi->setSalaire($lastUserMois->getSalaire());
                        $userMoi->setTauxEnveloppe($lastUserMois->getTauxEnveloppe());
                        $userMoi->setEpargne($lastUserMois->getEpargne());
                        
                        // Copier les UserDepenseFixe
                        foreach ($lastUserMois->getUserDepenseFixes() as $userDepenseFixe) {
                            $newUserDepenseFixe = new \App\Entity\Compte\UserDepenseFixe();
                            $newUserDepenseFixe->setNom($userDepenseFixe->getNom());
                            $newUserDepenseFixe->setMontant($userDepenseFixe->getMontant());
                            $newUserDepenseFixe->setIsDepenseCommune($userDepenseFixe->isDepenseCommune());
                            $newUserDepenseFixe->setUserMois($userMoi);
                            $entityManager->persist($newUserDepenseFixe);
                        }
                        
                        // Copier les Enveloppe
                        foreach ($lastUserMois->getEnveloppes() as $enveloppe) {
                            $newEnveloppe = new \App\Entity\Compte\Enveloppe();
                            $newEnveloppe->setNom($enveloppe->getNom());
                            $newEnveloppe->setMontant($enveloppe->getMontant());
                            $newEnveloppe->setPourcentage($enveloppe->getPourcentage());
                            $newEnveloppe->setUserMois($userMoi);
                            $entityManager->persist($newEnveloppe);
                        }
                    }
                }
                
                $entityManager->persist($userMoi);
            }
        }
        
        $entityManager->flush();
    }

    #[Route('/{id}', name: 'app_compte_exercice_show', methods: ['GET'])]
    public function show(Exercice $exercice): Response
    {
        return $this->render('compte/exercice/show.html.twig', [
            'exercice' => $exercice,
        ]);
    }

    #[Route('/{id}/update', name: 'app_compte_exercice_update', methods: ['POST'])]
    public function updateAide(Request $request, Exercice $exercice, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('update_aide'.$exercice->getId(), $request->request->get('_token'))) {
            $montantAide = $request->request->get('montantAide');
            if ($montantAide !== null) {
                $nouveauMontantAide = (int) $montantAide;
                
                $exercice->setMontantAide($nouveauMontantAide);
                $entityManager->flush();
            }
        }

        return $this->redirectToRoute('app_compte_exercice_show', ['id' => $exercice->getId()], Response::HTTP_SEE_OTHER);
    }
}
