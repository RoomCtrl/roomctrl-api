<?php

declare(strict_types=1);

namespace App\Tests\Feature\Booking\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Booking\Service\BookingService;
use App\Feature\Mail\Service\MailServiceInterface;
use App\Feature\Room\Entity\Room;
use App\Feature\Room\Repository\RoomRepository;
use App\Feature\User\Entity\User;
use App\Feature\User\Repository\UserRepository;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class BookingServiceTest extends TestCase
{
    private BookingRepository $bookingRepository;
    private RoomRepository $roomRepository;
    private UserRepository $userRepository;
    private MailServiceInterface $mailService;
    private BookingService $bookingService;

    protected function setUp(): void
    {
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $this->roomRepository = $this->createMock(RoomRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->mailService = $this->createMock(MailServiceInterface::class);

        $this->bookingService = new BookingService(
            $this->bookingRepository,
            $this->roomRepository,
            $this->userRepository,
            $this->mailService
        );
    }

    public function testCanUserCancelBookingReturnsTrueForOwner(): void
    {
        $userId = Uuid::v4();
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $booking = $this->createMock(Booking::class);
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn($userId);
        $booking->method('getUser')->willReturn($bookingUser);

        $result = $this->bookingService->canUserCancelBooking($booking, $user);

        $this->assertTrue($result);
    }

    public function testCanUserCancelBookingReturnsTrueForAdmin(): void
    {
        $userId = Uuid::v4();
        $bookingUserId = Uuid::v4();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN']);

        $booking = $this->createMock(Booking::class);
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn($bookingUserId);
        $booking->method('getUser')->willReturn($bookingUser);

        $result = $this->bookingService->canUserCancelBooking($booking, $user);

        $this->assertTrue($result);
    }

    public function testCanUserCancelBookingReturnsFalseForOtherUser(): void
    {
        $userId = Uuid::v4();
        $bookingUserId = Uuid::v4();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $booking = $this->createMock(Booking::class);
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn($bookingUserId);
        $booking->method('getUser')->willReturn($bookingUser);

        $result = $this->bookingService->canUserCancelBooking($booking, $user);

        $this->assertFalse($result);
    }

    public function testCanUserEditBookingReturnsTrueForOwner(): void
    {
        $userId = Uuid::v4();
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $booking = $this->createMock(Booking::class);
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn($userId);
        $booking->method('getUser')->willReturn($bookingUser);

        $result = $this->bookingService->canUserEditBooking($booking, $user);

        $this->assertTrue($result);
    }

    public function testCanUserEditBookingReturnsTrueForAdmin(): void
    {
        $userId = Uuid::v4();
        $bookingUserId = Uuid::v4();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        $user->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);

        $booking = $this->createMock(Booking::class);
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn($bookingUserId);
        $booking->method('getUser')->willReturn($bookingUser);

        $result = $this->bookingService->canUserEditBooking($booking, $user);

        $this->assertTrue($result);
    }

    public function testCanUserEditBookingReturnsFalseForOtherUser(): void
    {
        $userId = Uuid::v4();
        $bookingUserId = Uuid::v4();

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($userId);
        $user->method('getRoles')->willReturn(['ROLE_USER']);

        $booking = $this->createMock(Booking::class);
        $bookingUser = $this->createMock(User::class);
        $bookingUser->method('getId')->willReturn($bookingUserId);
        $booking->method('getUser')->willReturn($bookingUser);

        $result = $this->bookingService->canUserEditBooking($booking, $user);

        $this->assertFalse($result);
    }

    public function testCreateBookingThrowsExceptionForPastDate(): void
    {
        $room = $this->createMock(Room::class);
        $user = $this->createMock(User::class);
        $pastDate = new DateTimeImmutable('-1 day');
        $futureDate = new DateTimeImmutable('+1 day');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot create booking in the past');

        $this->bookingService->createBooking(
            'Test Meeting',
            $room,
            $user,
            $pastDate,
            $futureDate,
            5
        );
    }

    public function testCreateBookingThrowsExceptionWhenEndTimeBeforeStartTime(): void
    {
        $room = $this->createMock(Room::class);
        $user = $this->createMock(User::class);
        $startDate = new DateTimeImmutable('+2 days');
        $endDate = new DateTimeImmutable('+1 day');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('End time must be after start time');

        $this->bookingService->createBooking(
            'Test Meeting',
            $room,
            $user,
            $startDate,
            $endDate,
            5
        );
    }

    public function testCreateBookingThrowsExceptionWhenEndTimeEqualsStartTime(): void
    {
        $room = $this->createMock(Room::class);
        $user = $this->createMock(User::class);
        $date = new DateTimeImmutable('+1 day');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('End time must be after start time');

        $this->bookingService->createBooking(
            'Test Meeting',
            $room,
            $user,
            $date,
            $date,
            5
        );
    }

    public function testFindConflictingBookingCallsRepository(): void
    {
        $room = $this->createMock(Room::class);
        $startedAt = new DateTimeImmutable('+1 day');
        $endedAt = new DateTimeImmutable('+1 day +1 hour');
        $excludeId = Uuid::v4();

        $this->bookingRepository
            ->expects($this->once())
            ->method('findConflictingBooking')
            ->with($room, $startedAt, $endedAt, $excludeId)
            ->willReturn(null);

        $result = $this->bookingService->findConflictingBooking(
            $room,
            $startedAt,
            $endedAt,
            $excludeId
        );

        $this->assertNull($result);
    }
}
