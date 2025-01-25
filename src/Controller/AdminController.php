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
        $currentWeek = $today->format('W');
        $currentMonth = $today->format('Y-m');
        $currentYear = $today->format('Y');

        // Initialiser les totaux
        $totalByDay = 0;
        $totalByWeek = 0;
        $totalByMonth = 0;
        $totalByYear = 0;

        // Initialiser les compteurs de rendez-vous
        $countByDay = 0;
        $countByWeek = 0;
        $countByMonth = 0;
        $countByYear = 0;

        // Calculer les totaux et les quantités
        foreach ($appointments as $appointment) {
            $price = $appointment->getService()->getPrice(); 
            $appointmentDate = $appointment->getDate();
            if ($price > 0) {
                // Total par jour
                if ($appointmentDate->format('Y-m-d') === $today->format('Y-m-d')) {
                    $totalByDay += $price;
                    $countByDay++;
                }

                // Total par semaine
                if ($appointmentDate->format('W') === $currentWeek) {
                    $totalByWeek += $price;
                    $countByWeek++;
                }

                // Total par mois
                if ($appointmentDate->format('Y-m') === $currentMonth) {
                    $totalByMonth += $price;
                    $countByMonth++;
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
            'totalByYear' => $totalByYear,
            'countByDay' => $countByDay,
            'countByWeek' => $countByWeek,
            'countByMonth' => $countByMonth,
            'countByYear' => $countByYear,
        ]);
    }
}