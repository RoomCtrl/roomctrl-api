<?php

declare(strict_types=1);

namespace App\Feature\Mail\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Mail\DTO\ContactFormSentResponseDTO;
use App\Feature\Mail\DTO\MailSentResponseDTO;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

readonly class MailService implements MailServiceInterface
{
    private const string CONTACT_EMAIL = 'roomctrlinfo@gmail.com';

    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig,
        private string $fromAddress
    ) {
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
                'code' => Response::HTTP_BAD_REQUEST,
                'message' => 'Missing or empty required parameters: ' . implode(', ', $missingFields)
            ];
        }
        if (isset($data[$emailField])) {
            if (!filter_var($data[$emailField], FILTER_VALIDATE_EMAIL)) {
                return [
                    'code' => Response::HTTP_BAD_REQUEST,
                    'message' => 'Invalid email format'
                ];
            }
        }
        return null;
    }

    /**
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendEmail(array $data): MailSentResponseDTO
    {
        $sanitized = [
            'to' => isset($data['to']) ? $this->sanitizeMailField($data['to']) : '',
            'subject' => isset($data['subject']) ? $this->sanitizeMailField($data['subject']) : '',
            'content' => isset($data['content']) ? $this->sanitizeMailField($data['content']) : ''
        ];
        $htmlContent = $this->renderEmailTemplate($sanitized['content'], $sanitized['subject']);

        $email = new Email()
            ->from($this->fromAddress)
            ->to($sanitized['to'])
            ->subject($sanitized['subject'])
            ->text($sanitized['content'])
            ->html($htmlContent);

        $this->mailer->send($email);

        return new MailSentResponseDTO();
    }

    /**
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendContactFormEmail(array $data): ContactFormSentResponseDTO
    {
        $emailData = [
            'to' => self::CONTACT_EMAIL,
            'subject' => '[Contact Form] ' . $this->sanitizeMailField($data['subject']),
            'content' => $this->sanitizeMailField($this->formatContactMessage($data))
        ];
        $this->sendEmail($emailData);

        return new ContactFormSentResponseDTO();
    }

    /**
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendWelcomeEmail(User $user, mixed $organization): void
    {
        $html = $this->twig->render('emails/welcome_email.html.twig', [
            'user' => $user,
            'organization' => $organization,
        ]);

        $email = new Email()
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject('Welcome to RoomCtrl')
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendBookingConfirmation(User $user, Booking $booking, Room $room, array $participants = []): void
    {
        if (!$user->isEmailNotificationsEnabled()) {
            return;
        }

        $html = $this->twig->render('emails/booking_confirmation.html.twig', [
            'user' => $user,
            'booking' => $booking,
            'room' => $room,
            'participants' => $participants,
        ]);

        $email = new Email()
            ->from($this->fromAddress)
            ->to($user->getEmail())
            ->subject('Booking Confirmation: ' . $booking->getTitle())
            ->html($html);

        $this->mailer->send($email);
    }

    /**
     * @throws SyntaxError
     * @throws TransportExceptionInterface
     * @throws RuntimeError
     * @throws LoaderError
     */
    public function sendParticipantInvitation(User $participant, Booking $booking, Room $room, User $organizer): void
    {
        if (!$participant->isEmailNotificationsEnabled()) {
            return;
        }

        $html = $this->twig->render('emails/participant_invitation.html.twig', [
            'participant' => $participant,
            'booking' => $booking,
            'room' => $room,
            'organizer' => $organizer,
        ]);

        $email = new Email()
            ->from($this->fromAddress)
            ->to($participant->getEmail())
            ->subject('Meeting Invitation: ' . $booking->getTitle())
            ->html($html);

        $this->mailer->send($email);
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

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
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
            $value = preg_replace('#<' . $tag . '\b[^>]*>(.*?)</' . $tag . '>#is', '', $value);
            $value = preg_replace('#<' . $tag . '\b[^>]*>#is', '', $value);
        }
        return trim($value);
    }
}
