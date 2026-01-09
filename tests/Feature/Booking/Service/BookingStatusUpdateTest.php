<?php

declare(strict_types=1);

namespace App\Tests\Feature\Booking\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Booking\Service\BookingService;
use App\Feature\Mail\Service\MailServiceInterface;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Room;
use App\Feature\Room\Repository\RoomRepository;
use App\Feature\User\Entity\User;
use App\Feature\User\Repository\UserRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class BookingStatusUpdateTest extends TestCase
{
    private BookingService $bookingService;
    private BookingRepository $bookingRepository;

    protected function setUp(): void
    {
        $this->bookingRepository = $this->createMock(BookingRepository::class);
        $roomRepository = $this->createMock(RoomRepository::class);
        $userRepository = $this->createMock(UserRepository::class);
        $mailService = $this->createMock(MailServiceInterface::class);

        $this->bookingService = new BookingService(
            $this->bookingRepository,
            $roomRepository,
            $userRepository,
            $mailService
        );
    }

    public function testUpdateExpiredBookingStatusesWithNoExpiredBookings(): void
    {
        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $query->method('getResult')->willReturn([]);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $this->bookingRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $count = $this->bookingService->updateExpiredBookingStatuses();

        $this->assertEquals(0, $count);
    }

    public function testUpdateExpiredBookingStatusesWithExpiredBookings(): void
    {
        $booking1 = $this->createMock(Booking::class);
        $booking2 = $this->createMock(Booking::class);

        $booking1->expects($this->once())->method('setStatus')->with('completed');
        $booking2->expects($this->once())->method('setStatus')->with('completed');

        $queryBuilder = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $query = $this->createMock(\Doctrine\ORM\Query::class);
        
        $query->method('getResult')->willReturn([$booking1, $booking2]);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        
        $this->bookingRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $this->bookingRepository->expects($this->exactly(2))->method('save');
        $this->bookingRepository->expects($this->once())->method('flush');

        $count = $this->bookingService->updateExpiredBookingStatuses();

        $this->assertEquals(2, $count);
    }

    public function testBookingStatusLogic(): void
    {
        $now = new DateTimeImmutable();
        
        $booking = new Booking();
        $booking->setTitle('Test Booking');
        $booking->setStartedAt($now->modify('-2 hours'));
        $booking->setEndedAt($now->modify('-1 hour'));
        $booking->setParticipantsCount(5);
        $booking->setIsPrivate(false);
        $booking->setStatus('active');

        $room = new Room();
        $room->setRoomName('Test Room');
        $room->setCapacity(10);
        $room->setSize(25.5);
        $room->setLocation('Building A');
        $room->setAccess('public');

        $organization = new Organization();
        $organization->setRegon('123456789');
        $organization->setName('Test Org');
        $organization->setEmail('test@org.com');
        $room->setOrganization($organization);

        $user = new User();
        $user->setUsername('testuser');
        $user->setPassword('password');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setEmail('test@example.com');
        $user->setPhone('+48123456789');
        $user->setOrganization($organization);

        $booking->setRoom($room);
        $booking->setUser($user);

        $this->assertEquals('active', $booking->getStatus());
        $this->assertTrue($booking->getEndedAt() < $now);

        $booking->setStatus('completed');
        $this->assertEquals('completed', $booking->getStatus());
    }
}
