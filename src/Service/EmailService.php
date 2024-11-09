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
            ->subject("Bienvenue sur notre site, $username !")
            ->text('Bonjour');


        try {
            $this->mailer->send($email);
        } catch (\Exception $e) {
            throw new \Exception('Error sending welcome email: ' . $e->getMessage());
        }
    }
}