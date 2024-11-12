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
    public function senInfoWelcome(string $name, string $firstname, string $email): void
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
}