<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/administration/calendrier')]
class CalendarController extends AbstractController
{
    #[Route('/', name: 'app_calendar')]
    public function index()
    {
        return $this->render('calendar/index.html.twig');
    }


    #[Route('/creer-creneau', name: 'app_calendar_create_slot', methods: ['POST'])]
    public function createSlot(Request $request)
    {
        $data = json_decode($request->getContent(), true);
        // Logique pour sauvegarder le nouveau créneau en base de données
        // Utilisez $data['debut'] et $data['fin'] pour accéder aux données

        return new JsonResponse(['succes' => true]);
    }
}