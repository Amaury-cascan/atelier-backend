<?php

namespace App\Controller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Repository\AppointmentRepository;
use DateTime;
use DateTimeZone;


#[Route('/administration')]
class AdminController extends AbstractController
{
    #[Route('/', name: 'app_dashboard_index', methods: ['GET'])]
    public function dashboard(AppointmentRepository $appointmentRepository): Response
    {
        // Récupérer tous les rendez-vous
        $appointments = $appointmentRepository->findAll();

        // Obtenir la date actuelle
        $timezone = new DateTimeZone('Europe/Paris');
        $today = new DateTime('now', $timezone);
        $formatToday = $today->format('Y-m-d');
        $currentWeek = $today->format('Y-W');
        $currentMonth = $today->format('Y-m');
        $currentYear = $today->format('Y');
        
        // le mois d'avant
        $lastMonth = (clone $today)->modify('-1 month')->format('Y-m');
        // le mois encore avant 
        $lastMonth2 = (clone $today)->modify('-2 months')->format('Y-m');
        // le mois encore avant 
        $lastMonth3 = (clone $today)->modify('-3 months')->format('Y-m');

        // Initialiser les totaux
        $totalByDay = 0;
        $totalByWeek = 0;
        $totalByMonth = 0;
        $totalByMonth1 = 0;
        $totalByMonth2 = 0;
        $totalByMonth3 = 0;
        $totalByYear = 0;

        // Initialiser les compteurs de rendez-vous
        $countByDay = 0;
        $countByWeek = 0;
        $countByMonth = 0;
        $countByMonth1 = 0;
        $countByMonth2 = 0;
        $countByMonth3 = 0;
        $countByYear = 0;

        // Calculer les totaux et les quantités
        foreach ($appointments as $appointment) {
            $price = $appointment->getPrice() ?? 0; 
            $appointmentDate = $appointment->getDate();
            if ($price > 0) {
                // Total par jour
                if ($appointmentDate->format('Y-m-d') === $formatToday) {
                    $totalByDay += $price;
                    $countByDay++;
                }

                // Total par semaine
                if ($appointmentDate->format('Y-W') === $currentWeek) {
                    $totalByWeek += $price;
                    $countByWeek++;
                }

                // Total par mois
                if ($appointmentDate->format('Y-m') === $currentMonth) {
                    $totalByMonth += $price;
                    $countByMonth++;
                }
                //Total mois d'avant
                if ($appointmentDate->format('Y-m') === $lastMonth) {
                    $totalByMonth1 += $price;
                    $countByMonth1++;
                }
                //Total mois d'avant
                if ($appointmentDate->format('Y-m') === $lastMonth2) {
                    $totalByMonth2 += $price;
                    $countByMonth2++;
                }
                //Total mois d'avant
                if ($appointmentDate->format('Y-m') === $lastMonth3) {
                    $totalByMonth3 += $price;
                    $countByMonth3++;
                }

                // Total par an
                if ($appointmentDate->format('Y') === $currentYear) {
                    $totalByYear += $price;
                    $countByYear++;
                }
            }
        }

        return $this->render('admin/dashboard.html.twig', [
            'totalByDay' => $totalByDay,
            'totalByWeek' => $totalByWeek,
            'totalByMonth' => $totalByMonth,
            'totalByMonth1' => $totalByMonth1,
            'totalByMonth2' => $totalByMonth2,
            'totalByMonth3' => $totalByMonth3,
            'totalByYear' => $totalByYear,
            'countByDay' => $countByDay,
            'countByWeek' => $countByWeek,
            'countByMonth' => $countByMonth,
            'countByYear' => $countByYear,
        ]);
    }
}