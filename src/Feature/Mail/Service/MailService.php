<?php

declare(strict_types=1);

namespace App\Feature\Mail\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailService
{
    private MailerInterface $mailer;
    private string $fromAddress;
    private Environment $twig;
    private string $contactEmail;

    public function __construct(MailerInterface $mailer, Environment $twig, ?string $fromAddress = null)
    {
        $this->mailer = $mailer;
        $this->twig = $twig;
        $this->fromAddress = $fromAddress ?? $_ENV['MAIL_FROM_ADDRESS'];
        $this->contactEmail = 'roomctrlinfo@gmail.com';
    }

    public function validateMailFields(array $data, array $requiredFields, string $emailField = 'email'): ?array
    {
        $missingFields = [];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || !is_string($data[$field]) || trim($data[$field]) === '') {
                $missingFields[] = $field;
            }
        }
        if (count($missingFields) > 0) {
            return [
                'code' => 400,
                'message' => 'Missing or empty required parameters: ' . implode(', ', $missingFields)
            ];
        }
        if (isset($data[$emailField])) {
            if (!filter_var($data[$emailField], FILTER_VALIDATE_EMAIL)) {
                return [
                    'code' => 400,
                    'message' => 'Invalid email format'
                ];
            }
        }
        return null;
    }

    public function sendEmail(array $data): array
    {
        $sanitized = [
            'to' => isset($data['to']) ? $this->sanitizeMailField($data['to']) : '',
            'subject' => isset($data['subject']) ? $this->sanitizeMailField($data['subject']) : '',
            'content' => isset($data['content']) ? $this->sanitizeMailField($data['content']) : ''
        ];
        $htmlContent = $this->renderEmailTemplate($sanitized['content'], $sanitized['subject']);

        $email = (new Email())
            ->from($this->fromAddress)
            ->to($sanitized['to'])
            ->subject($sanitized['subject'])
            ->text($sanitized['content'])
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
            'subject' => '[Contact Form] ' . $this->sanitizeMailField($data['subject']),
            'content' => $this->sanitizeMailField($this->formatContactMessage($data))
        ];
        return $this->sendEmail($emailData);
    }

    private function formatContactMessage(array $data): string
    {
        return "Message from contact form:\n\n" .
            "From: " . $this->sanitizeMailField($data['name']) . " <" . $this->sanitizeMailField($data['email']) . ">\n" .
            "Subject: " . $this->sanitizeMailField($data['subject']) . "\n\n" .
            "Message content:\n" . $this->sanitizeMailField($data['message']) . "\n\n" .
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

    private function sanitizeMailField($value): string
    {
        if (!is_string($value)) {
            return '';
        }

        $dangerous = ['script', 'iframe', 'style', 'object', 'embed'];
        foreach ($dangerous as $tag) {
            $value = preg_replace('#<'.$tag.'\b[^>]*>(.*?)</'.$tag.'>#is', '', $value);
            $value = preg_replace('#<'.$tag.'\b[^>]*>#is', '', $value);
        }
        $value = trim($value);
        return $value;
    }
}
