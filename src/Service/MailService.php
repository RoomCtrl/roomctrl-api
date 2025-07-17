<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailService
{
    private MailerInterface $mailer;
    private string $fromAddress;
    private Environment $twig;
    private string $contactEmail;

    public function __construct(MailerInterface $mailer, Environment $twig, string $fromAddress = null)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->fromAddress = $fromAddress ?? $_ENV['MAIL_FROM_ADDRESS'];
        $this->contactEmail = 'roomctrlinfo@gmail.com';
    }

    public function validateEmailData(array $data): ?array
    {
        if (!isset($data['to']) || !isset($data['subject']) || !isset($data['content'])) {
            return [
                'code' => 400,
                'message' => 'Missing required parameters: to, subject, content'
            ];
        }

        return null;
    }

    public function validateContactFormData(array $data): ?array
    {
        if (!isset($data['name']) || !isset($data['email']) || !isset($data['subject']) || !isset($data['message'])) {
            return [
                'code' => 400,
                'message' => 'Missing required parameters: name, email, subject, message'
            ];
        }

        return null;
    }

    public function sendEmail(array $data): array
    {
        $htmlContent = $this->renderEmailTemplate($data['content'], $data['subject']);

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($data['to'])
            ->subject($data['subject'])
            ->text($data['content'])
            ->html($htmlContent);

        $this->mailer->send($email);

        return [
            'code' => 200,
            'message' => 'Email has been sent successfully'
        ];
    }

    public function sendContactFormEmail(array $data): array
    {
        $emailData = [
            'to' => $this->contactEmail,
            'subject' => '[Contact Form] ' . $data['subject'],
            'content' => $this->formatContactMessage($data)
        ];

        return $this->sendEmail($emailData);
    }

    private function formatContactMessage(array $data): string
    {
        return "Message from contact form:\n\n" .
               "From: " . $data['name'] . " <" . $data['email'] . ">\n" .
               "Subject: " . $data['subject'] . "\n\n" .
               "Message content:\n" . $data['message'] . "\n\n" .
               "---\n" .
               "This message was sent through the website contact form.";
    }

    private function renderEmailTemplate(string $content, string $subject): string
    {
        $htmlContent = nl2br(htmlspecialchars($content));

        return $this->twig->render('emails/standard_email.html.twig', [
            'subject' => $subject,
            'content' => $htmlContent
        ]);
    }
}
