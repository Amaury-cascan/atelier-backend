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

#[Route('/administration/statistiques')]
class StatisticsController extends AbstractController
{
    #[Route('/', name: 'app_statistics_index', methods: ['GET'])]
    public function index(AppointmentRepository $appointmentRepository, Request $request): Response
    {
        // Obtenir la date actuelle
        $timezone = new DateTimeZone('Europe/Paris');
        $today = new DateTime('now', $timezone);

        // Récupérer la période depuis les paramètres de la requête
        $period = $request->query->get('period', 'daily'); // Valeur par défaut : 'daily'

        // Récupérer la date, la semaine, le mois et l'année depuis les paramètres de la requête
        $date = $request->query->get('date', $today->format('Y-m-d')); // Utiliser la date actuelle
        $week = $request->query->get('week', $today->format('W-Y')); // Utiliser l'année ISO et la semaine actuelle
        $month = $request->query->get('month', $today->format('m-Y')); // Utiliser l'année et le mois actuels
        $year = $request->query->get('year', $today->format('Y')); // Utiliser l'année actuelle
        
        // Récupérer tous les rendez-vous
        $appointments = $appointmentRepository->findAll();

        // Initialiser les tableaux pour stocker les statistiques
        $dailyStats = [];
        $weeklyStats = [];
        $monthlyStats = [];
        $yearlyStats = [];

        // Calculer les statistiques
        foreach ($appointments as $appointment) {
            $price = $appointment->getPrice() ?? 0; 
            $appointmentDate = $appointment->getDate(); 
            $serviceName = $appointment->getService()?->getName() ?? 'Sans service';

            // Statistiques par jour
            $dayKey = $appointmentDate->format('Y-m-d');
            if (!isset($dailyStats[$dayKey][$serviceName])) {
                $dailyStats[$dayKey][$serviceName] = ['count' => 0, 'total' => 0];
            }
            $dailyStats[$dayKey][$serviceName]['count']++;
            $dailyStats[$dayKey][$serviceName]['total'] += $price;

            // Statistiques par semaine
            $weekKey = $appointmentDate->format('W-Y'); // Utiliser l'année ISO et la semaine
            if (!isset($weeklyStats[$weekKey][$serviceName])) {
                $weeklyStats[$weekKey][$serviceName] = ['count' => 0, 'total' => 0];
            }
            $weeklyStats[$weekKey][$serviceName]['count']++;
            $weeklyStats[$weekKey][$serviceName]['total'] += $price;

            // Statistiques par mois
            $monthKey = $appointmentDate->format('m-Y');
            if (!isset($monthlyStats[$monthKey][$serviceName])) {
                $monthlyStats[$monthKey][$serviceName] = ['count' => 0, 'total' => 0];
            }
            $monthlyStats[$monthKey][$serviceName]['count']++;
            $monthlyStats[$monthKey][$serviceName]['total'] += $price;

            // Statistiques par année
            $yearKey = $appointmentDate->format('Y');
            if (!isset($yearlyStats[$yearKey][$serviceName])) {
                $yearlyStats[$yearKey][$serviceName] = ['count' => 0, 'total' => 0];
            }
            $yearlyStats[$yearKey][$serviceName]['count']++;
            $yearlyStats[$yearKey][$serviceName]['total'] += $price;
        }

        // Calculer les totaux par jour, semaine, mois et année
        $totalDaily = [];
        foreach ($dailyStats as $day => $services) {
            $totalDaily[$day] = ['count' => 0, 'total' => 0];
            foreach ($services as $service => $stats) {
                $totalDaily[$day]['count'] += $stats['count'];
                $totalDaily[$day]['total'] += $stats['total'];
            }
        }

        $totalWeekly = [];
        foreach ($weeklyStats as $weekKey => $services) {
            $totalWeekly[$weekKey] = ['count' => 0, 'total' => 0];
            foreach ($services as $service => $stats) {
                $totalWeekly[$weekKey]['count'] += $stats['count'];
                $totalWeekly[$weekKey]['total'] += $stats['total'];
            }
        }

        $totalMonthly = [];
        foreach ($monthlyStats as $monthKey => $services) {
            $totalMonthly[$monthKey] = ['count' => 0, 'total' => 0];
            foreach ($services as $service => $stats) {
                $totalMonthly[$monthKey]['count'] += $stats['count'];
                $totalMonthly[$monthKey]['total'] += $stats['total'];
            }
        }
        $totalYearly = [];
        foreach ($yearlyStats as $yearKey => $services) {
            $totalYearly[$yearKey] = ['count' => 0, 'total' => 0];
            foreach ($services as $service => $stats) {
                $totalYearly[$yearKey]['count'] += $stats['count'];
                $totalYearly[$yearKey]['total'] += $stats['total'];
            }
        }
       
        // Vérifiez si des statistiques existent pour la date demandée
        $hasDailyStats = isset($dailyStats[$date]) && !empty($dailyStats[$date]);
        $hasWeeklyStats = isset($weeklyStats[$week]) && !empty($weeklyStats[$week]);
        $hasMonthlyStats = isset($monthlyStats[$month]) && !empty($monthlyStats[$month]);
        $hasYearlyStats = isset($yearlyStats[$year]) && !empty($yearlyStats[$year]);

        return $this->render('statistics/statistics.html.twig', [
            'dailyStats' => $dailyStats,
            'weeklyStats' => $weeklyStats,
            'monthlyStats' => $monthlyStats,
            'yearlyStats' => $yearlyStats,
            'totalDaily' => $totalDaily,
            'totalWeekly' => $totalWeekly,
            'totalMonthly' => $totalMonthly,
            'totalYearly' => $totalYearly,
            'period' => $period,
            'date' => $date,
            'week' => $week,
            'month' => $month,
            'year' => $year,
            'hasDailyStats' => $hasDailyStats,
            'hasWeeklyStats' => $hasWeeklyStats,
            'hasMonthlyStats' => $hasMonthlyStats,
            'hasYearlyStats' => $hasYearlyStats,
        ]);
    }
}