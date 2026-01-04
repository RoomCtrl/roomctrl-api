<?php

declare(strict_types=1);

namespace App\Tests\Feature\Booking\Entity;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class BookingTest extends TestCase
{
    public function testBookingCreation(): void
    {
        $booking = new Booking();
        
        $this->assertNull($booking->getId());
        $this->assertInstanceOf(DateTimeImmutable::class, $booking->getCreatedAt());
        $this->assertCount(0, $booking->getParticipants());
    }

    public function testSetAndGetTitle(): void
    {
        $booking = new Booking();
        $title = 'Team Meeting';
        
        $result = $booking->setTitle($title);
        
        $this->assertSame($booking, $result);
        $this->assertEquals($title, $booking->getTitle());
    }

    public function testSetAndGetStartedAt(): void
    {
        $booking = new Booking();
        $startedAt = new DateTimeImmutable('2026-01-15 10:00:00');
        
        $result = $booking->setStartedAt($startedAt);
        
        $this->assertSame($booking, $result);
        $this->assertSame($startedAt, $booking->getStartedAt());
    }

    public function testSetAndGetEndedAt(): void
    {
        $booking = new Booking();
        $endedAt = new DateTimeImmutable('2026-01-15 11:00:00');
        
        $result = $booking->setEndedAt($endedAt);
        
        $this->assertSame($booking, $result);
        $this->assertSame($endedAt, $booking->getEndedAt());
    }

    public function testSetAndGetParticipantsCount(): void
    {
        $booking = new Booking();
        $count = 10;
        
        $result = $booking->setParticipantsCount($count);
        
        $this->assertSame($booking, $result);
        $this->assertEquals($count, $booking->getParticipantsCount());
    }

    public function testSetAndGetIsPrivate(): void
    {
        $booking = new Booking();
        
        $result = $booking->setIsPrivate(true);
        
        $this->assertSame($booking, $result);
        $this->assertTrue($booking->isPrivate());
        
        $booking->setIsPrivate(false);
        $this->assertFalse($booking->isPrivate());
    }

    public function testSetAndGetStatus(): void
    {
        $booking = new Booking();
        $status = 'cancelled';
        
        $result = $booking->setStatus($status);
        
        $this->assertSame($booking, $result);
        $this->assertEquals($status, $booking->getStatus());
    }

    public function testSetAndGetRoom(): void
    {
        $booking = new Booking();
        $room = $this->createMock(Room::class);
        
        $result = $booking->setRoom($room);
        
        $this->assertSame($booking, $result);
        $this->assertSame($room, $booking->getRoom());
    }

    public function testSetAndGetUser(): void
    {
        $booking = new Booking();
        $user = $this->createMock(User::class);
        
        $result = $booking->setUser($user);
        
        $this->assertSame($booking, $result);
        $this->assertSame($user, $booking->getUser());
    }

    public function testAddParticipant(): void
    {
        $booking = new Booking();
        $user = $this->createMock(User::class);
        
        $result = $booking->addParticipant($user);
        
        $this->assertSame($booking, $result);
        $this->assertCount(1, $booking->getParticipants());
        $this->assertTrue($booking->getParticipants()->contains($user));
    }

    public function testAddParticipantDoesNotAddDuplicates(): void
    {
        $booking = new Booking();
        $user = $this->createMock(User::class);
        
        $booking->addParticipant($user);
        $booking->addParticipant($user);
        
        $this->assertCount(1, $booking->getParticipants());
    }

    public function testRemoveParticipant(): void
    {
        $booking = new Booking();
        $user = $this->createMock(User::class);
        
        $booking->addParticipant($user);
        $this->assertCount(1, $booking->getParticipants());
        
        $result = $booking->removeParticipant($user);
        
        $this->assertSame($booking, $result);
        $this->assertCount(0, $booking->getParticipants());
        $this->assertFalse($booking->getParticipants()->contains($user));
    }

    public function testFluentInterface(): void
    {
        $booking = new Booking();
        $room = $this->createMock(Room::class);
        $user = $this->createMock(User::class);
        $startedAt = new DateTimeImmutable('2026-01-15 10:00:00');
        $endedAt = new DateTimeImmutable('2026-01-15 11:00:00');
        
        $result = $booking
            ->setTitle('Meeting')
            ->setStartedAt($startedAt)
            ->setEndedAt($endedAt)
            ->setParticipantsCount(5)
            ->setIsPrivate(true)
            ->setStatus('active')
            ->setRoom($room)
            ->setUser($user);
        
        $this->assertSame($booking, $result);
        $this->assertEquals('Meeting', $booking->getTitle());
        $this->assertSame($startedAt, $booking->getStartedAt());
        $this->assertSame($endedAt, $booking->getEndedAt());
        $this->assertEquals(5, $booking->getParticipantsCount());
        $this->assertTrue($booking->isPrivate());
        $this->assertEquals('active', $booking->getStatus());
        $this->assertSame($room, $booking->getRoom());
        $this->assertSame($user, $booking->getUser());
    }
}
