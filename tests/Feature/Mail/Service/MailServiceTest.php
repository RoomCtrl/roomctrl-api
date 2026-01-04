<?php

declare(strict_types=1);

namespace App\Tests\Feature\Mail\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Mail\Service\MailService;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailServiceTest extends TestCase
{
    private MailService $mailService;
    private MailerInterface $mailer;
    private Environment $twig;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->twig = $this->createMock(Environment::class);

        $this->mailService = new MailService(
            $this->mailer,
            $this->twig,
            'noreply@example.com'
        );
    }

    public function testValidateMailFieldsReturnsMissingFieldsError(): void
    {
        $data = [
            'email' => 'test@example.com',
            // subject is missing
            'content' => 'Test content'
        ];

        $requiredFields = ['email', 'subject', 'content'];

        $result = $this->mailService->validateMailFields($data, $requiredFields);

        $this->assertNotNull($result);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $result['code']);
        $this->assertStringContainsString('Missing or empty required parameters', $result['message']);
        $this->assertStringContainsString('subject', $result['message']);
    }

    public function testValidateMailFieldsReturnsInvalidEmailError(): void
    {
        $data = [
            'email' => 'invalid-email',
            'subject' => 'Test Subject',
            'content' => 'Test content'
        ];

        $requiredFields = ['email', 'subject', 'content'];

        $result = $this->mailService->validateMailFields($data, $requiredFields);

        $this->assertNotNull($result);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $result['code']);
        $this->assertStringContainsString('Invalid email format', $result['message']);
    }

    public function testValidateMailFieldsReturnsNullForValidData(): void
    {
        $data = [
            'email' => 'valid@example.com',
            'subject' => 'Test Subject',
            'content' => 'Test content'
        ];

        $requiredFields = ['email', 'subject', 'content'];

        $result = $this->mailService->validateMailFields($data, $requiredFields);

        $this->assertNull($result);
    }

    public function testValidateMailFieldsReturnsErrorForEmptyFields(): void
    {
        $data = [
            'email' => '',
            'subject' => 'Test Subject',
            'content' => 'Test content'
        ];

        $requiredFields = ['email', 'subject', 'content'];

        $result = $this->mailService->validateMailFields($data, $requiredFields);

        $this->assertNotNull($result);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $result['code']);
        $this->assertStringContainsString('Missing or empty required parameters', $result['message']);
    }

    public function testSendEmailCallsMailerWithCorrectParameters(): void
    {
        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with('emails/standard_email.html.twig')
            ->willReturn('<html><body>Test email</body></html>');

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'recipient@example.com'
                    && $email->getFrom()[0]->getAddress() === 'noreply@example.com'
                    && $email->getSubject() === 'Test Subject';
            }));

        $data = [
            'to' => 'recipient@example.com',
            'subject' => 'Test Subject',
            'content' => 'Test content'
        ];

        $result = $this->mailService->sendEmail($data);

        $this->assertNotNull($result);
    }

    public function testSendWelcomeEmailCallsMailerWithCorrectTemplate(): void
    {
        $user = $this->createMock(User::class);
        $organization = $this->createMock(\App\Feature\Organization\Entity\Organization::class);

        $user->method('getEmail')->willReturn('newuser@example.com');

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with('emails/welcome_email.html.twig', [
                'user' => $user,
                'organization' => $organization
            ])
            ->willReturn('<html><body>Welcome</body></html>');

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'newuser@example.com'
                    && $email->getSubject() === 'Welcome to RoomCtrl';
            }));

        $this->mailService->sendWelcomeEmail($user, $organization);
    }

    public function testSendBookingConfirmationCallsMailerWhenNotificationsEnabled(): void
    {
        $user = $this->createMock(User::class);
        $booking = $this->createMock(Booking::class);
        $room = $this->createMock(Room::class);

        $user->method('getEmail')->willReturn('user@example.com');
        $user->method('isEmailNotificationsEnabled')->willReturn(true);
        
        $booking->method('getTitle')->willReturn('Meeting Room Booking');

        $this->twig
            ->expects($this->once())
            ->method('render')
            ->with('emails/booking_confirmation.html.twig')
            ->willReturn('<html><body>Booking confirmed</body></html>');

        $this->mailer
            ->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Email $email) {
                return $email->getTo()[0]->getAddress() === 'user@example.com'
                    && str_contains($email->getSubject(), 'Booking Confirmation');
            }));

        $this->mailService->sendBookingConfirmation($user, $booking, $room);
    }

    public function testSendBookingConfirmationDoesNotSendWhenNotificationsDisabled(): void
    {
        $user = $this->createMock(User::class);
        $booking = $this->createMock(Booking::class);
        $room = $this->createMock(Room::class);

        $user->method('isEmailNotificationsEnabled')->willReturn(false);

        $this->twig
            ->expects($this->never())
            ->method('render');

        $this->mailer
            ->expects($this->never())
            ->method('send');

        $this->mailService->sendBookingConfirmation($user, $booking, $room);
    }
}
