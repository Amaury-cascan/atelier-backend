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
            ->text("Bonjour et bienvenue, $username !\n\n"
                . "Nous vous remercions d'avoir créé un compte.\n\n"
                . "Vous pouvez dès à présent prendre rendez-vous facilement via notre site et profiter de nos prestations.\n\n"
                . "Nous sommes ravis de vous avoir parmi nous et restons à votre disposition pour toute question.\n\n"
                . "À très bientôt !");


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
            ->to("latelierdemarie41@outlook.com")
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