<?php

namespace App\Command;

use App\Repository\AppointmentRepository;
use App\Service\EmailService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:send-appointment-reminders',
    description: 'Envoie des rappels par e-mail pour les rendez-vous du lendemain.',
)]
class SendAppointmentRemindersCommand extends Command
{
    private $appointmentRepository;
    private $emailService;

    public function __construct(AppointmentRepository $appointmentRepository, EmailService $emailService)
    {
        $this->appointmentRepository = $appointmentRepository;
        $this->emailService = $emailService;

        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('Cette commande permet d\'envoyer des e-mails de rappel aux clients ayant un rendez-vous prévu pour le lendemain.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Début de l\'envoi des rappels de rendez-vous');

        // Utilise le fuseau horaire de Paris pour être sûr
        $tomorrow = new \DateTime('tomorrow', new \DateTimeZone('Europe/Paris'));
        
        $appointments = $this->appointmentRepository->findAppointmentsForDate($tomorrow);

        if (empty($appointments)) {
            $io->info('Aucun rendez-vous trouvé pour demain. Aucune action requise.');
            return Command::SUCCESS;
        }

        $io->comment(count($appointments) . ' rendez-vous à notifier.');
        $io->progressStart(count($appointments));

        $successCount = 0;
        foreach ($appointments as $appointment) {
            $client = $appointment->getClient();
            $service = $appointment->getService();

            if ($client && $service && $client->getEmail()) {
                $clientName = $client->getFirstName() . ' ' . $client->getName();
                try {
                    $this->emailService->sendAppointmentReminderEmail(
                        $client->getEmail(),
                        $clientName,
                        $service->getName(),
                        $appointment->getDate()
                    );
                    $successCount++;
                } catch (\Exception $e) {
                    $io->error('Impossible d\'envoyer l\'e-mail pour le RDV ID ' . $appointment->getId() . ': ' . $e->getMessage());
                }
            } else {
                $io->warning('RDV ID ' . $appointment->getId() . ' ignoré (client, e-mail ou service manquant).');
            }
            $io->progressAdvance();
        }

        $io->progressFinish();
        $io->success($successCount . ' rappels ont été envoyés avec succès !');
        

        // Récapitulatif quotidien : uniquement les boîtes du salon (pas le prestataire informatique).
        $recipients = [
            'latelierdemarie41@outlook.com',
            'marie.pacreau14@outlook.fr',
        ];

        $summarySuccessCount = 0;
        foreach ($recipients as $recipient) {
            try {
                $this->emailService->sendDailyAppointmentsSummary(
                    $recipient,
                    $appointments,
                    $tomorrow
                );
                $summarySuccessCount++;
            } catch (\Exception $e) {
                $io->error('Impossible d\'envoyer le récapitulatif à ' . $recipient . ' : ' . $e->getMessage());
            }
        }
        
        $io->info($summarySuccessCount . ' récapitulatifs quotidiens envoyés sur ' . count($recipients));

        return Command::SUCCESS;
    }
} 