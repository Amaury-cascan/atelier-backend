<?php

namespace App\Service;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailService
{
    private MailerInterface $mailer;
    private string $fromEmail;
    private string $fromName;

    public function __construct(MailerInterface $mailer, string $fromEmail, string $fromName)
    {
        $this->mailer = $mailer;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
    }

    public function sendWelcomeEmail(string $to, string $username): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($to)
            ->subject("Bienvenue sur L'atelier de Marie !")
            ->html(
                "<p>Bonjour et bienvenue, $username !</p>" .
                "<p>Nous vous remercions d'avoir créé un compte.</p>" .
                "<p>Vous pouvez dès à présent prendre rendez-vous facilement via notre site et profiter de nos prestations.</p>" .
                "<p>Nous sommes ravis de vous avoir parmi nous et restons à votre disposition pour toute question.</p>" .
                "<p>À très bientôt !</p>" .
                "<p><strong>L'atelier de Marie</strong><br>" .
                "06.60.53.50.44</p>" .
                "<img src='https://www.backoffice.atelier-de-marie.com/images/1.ico' alt='Atelier de Marie' />"
            );

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            throw new \Exception('Error sending welcome email: ' . $e->getMessage());
        }
    }
    public function sendInfoWelcome(string $name, string $firstname, string $email): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to("latelierdemarie41@outlook.com", "marie.pacreau14@outlook.fr")
            ->subject("Nouveau client !")
            ->text("Bonjour,\n\n"
                . "Un nouveau client a créé un compte.\n\n"
                . "Voici les détails du nouveau client :\n\n"
                . "- **Nom** : " . $firstname . ' ' . $name . "\n"
                . "- **Email** : " . $email . "\n\n"
            );


        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            throw new \Exception('Error sending email: ' . $e->getMessage());
        }
    }

    public function sendRdvToClient(string $date, string $firstname, string $name, string $to, string $service): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($to)
            ->subject("Confirmation de votre rendez-vous")
            ->text("Bonjour " . $firstname . " " . $name .",\n\n"
                . "Nous vous confirmons la prise en compte de votre rendez-vous.\n\n"
                . "Détails du rendez-vous :\n"
                . "- Service : " . $service . "\n"
                . "- Date et heure : " . $date . "\n\n"
                . "Si vous souhaitez modifier ce rendez-vous, merci de contacter Marie au 06.60.53.50.44.\n\n"
                . "À très bientôt à l'Atelier de Marie !\n\n"
                . "https://atelier-de-marie.com\n\n"
            );

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            throw new \Exception('Error sending email: ' . $e->getMessage());
        }
    }

    public function sendRdvToMarie(string $date, string $firstname,string $name, string $service): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to("latelierdemarie41@outlook.com", "marie.pacreau14@outlook.fr")
            ->subject("Nouveau rendez-vous")
            ->text("Nouveau RDV de " . $firstname . " " . $name ."\n\n"
                . "Détails du rendez-vous :\n"
                . "- Service : " . $service . "\n"
                . "- Date et heure : " . $date . "\n\n"
            );

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            throw new \Exception('Error sending email: ' . $e->getMessage());
        }
    }

    public function sendPasswordResetEmail(string $to, string $username, string $resetToken, string $frontendUrl = null): void
    {
        $resetUrl = ($frontendUrl ?? 'https://atelier-de-marie.com') . '/reset-password?token=' . $resetToken;
        
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($to)
            ->subject("Réinitialisation de votre mot de passe - L'Atelier de Marie")
            ->html(
                "<p>Bonjour " . $username . ",</p>" .
                "<p>Vous avez demandé la réinitialisation de votre mot de passe pour votre compte sur L'Atelier de Marie.</p>" .
                "<p>Pour créer un nouveau mot de passe, cliquez sur le lien ci-dessous :</p>" .
                "<p><a href='" . $resetUrl . "' style='background-color: #a24e32; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Réinitialiser mon mot de passe</a></p>" .
                "<p>Ou copiez ce lien dans votre navigateur :</p>" .
                "<p>" . $resetUrl . "</p>" .
                "<p><strong>Important :</strong> Ce lien expire dans 1 heure pour des raisons de sécurité.</p>" .
                "<p>Si vous n'avez pas demandé cette réinitialisation, vous pouvez ignorer cet email en toute sécurité.</p>" .
                "<p>À très bientôt à l'Atelier de Marie !</p>" .
                "<p>https://atelier-de-marie.com</p>" .
                "<p>06.60.53.50.44</p>"
            );

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            throw new \Exception('Error sending password reset email: ' . $e->getMessage());
        }
    }

    public function sendAppointmentReminderEmail(string $to, string $username, string $serviceName, \DateTimeInterface $appointmentDate): void
    {
        $formattedDate = $appointmentDate->format('d/m/Y à H:i');

        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to('amaury.cascan@hotmail.fr')
            ->subject("Rappel de votre rendez-vous demain - L'Atelier de Marie")
            ->html(
                "<p>Bonjour " . $username . ",</p>" .
                "<p>Ceci est un petit rappel pour votre rendez-vous de demain à L'Atelier de Marie.</p>" .
                "<p><strong>Détails du rendez-vous :</strong></p>" .
                "<ul>" .
                "<li><strong>Service :</strong> " . $serviceName . "</li>" .
                "<li><strong>Date et heure :</strong> " . $formattedDate . "</li>" .
                "</ul>" .
                "<p>En cas d'empêchement, merci de prévenir Marie au plus vite au 06.60.53.50.44 afin de libérer le créneau.</p>" .
                "<p>À très bientôt à l'Atelier de Marie !</p>" .
                "<p>https://atelier-de-marie.com</p>"
            );

        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            // Log l'erreur au lieu de la propager pour ne pas bloquer la commande
            // Par exemple, avec un service de log : $this->logger->error(...)
            throw new \Exception('Error sending appointment reminder email: ' . $e->getMessage());
        }
    }
}