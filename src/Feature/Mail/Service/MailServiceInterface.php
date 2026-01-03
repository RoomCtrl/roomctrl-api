<?php

declare(strict_types=1);

namespace App\Feature\Mail\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Mail\DTO\ContactFormSentResponseDTO;
use App\Feature\Mail\DTO\MailSentResponseDTO;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;

interface MailServiceInterface
{
    /**
     * Validate mail fields
     *
     * @param array<string, mixed> $data
     * @param array<int, string> $requiredFields
     * @return array<string, mixed>|null
     */
    public function validateMailFields(array $data, array $requiredFields, string $emailField = 'email'): ?array;

    /**
     * Send generic email
     *
     * @param array<string, mixed> $data
     */
    public function sendEmail(array $data): MailSentResponseDTO;

    /**
     * Send contact form email
     *
     * @param array<string, mixed> $data
     */
    public function sendContactFormEmail(array $data): ContactFormSentResponseDTO;

    /**
     * Send welcome email to new user
     */
    public function sendWelcomeEmail(User $user, mixed $organization): void;

    /**
     * Send booking confirmation email
     *
     * @param array<int, User> $participants
     */
    public function sendBookingConfirmation(User $user, Booking $booking, Room $room, array $participants = []): void;

    /**
     * Send meeting invitation to participant
     */
    public function sendParticipantInvitation(User $participant, Booking $booking, Room $room, User $organizer): void;
}
