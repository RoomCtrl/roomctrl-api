<?php

declare(strict_types=1);

namespace App\Tests\Feature\Room\Entity;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Equipment;
use App\Feature\Room\Entity\Room;
use App\Feature\Room\Entity\RoomStatus;
use App\Feature\User\Entity\User;
use PHPUnit\Framework\TestCase;

class RoomTest extends TestCase
{
    public function testRoomCreation(): void
    {
        $room = new Room();
        
        $this->assertNull($room->getId());
        $this->assertCount(0, $room->getEquipment());
        $this->assertCount(0, $room->getFavoritedByUsers());
        $this->assertCount(0, $room->getBookings());
    }

    public function testSetAndGetRoomName(): void
    {
        $room = new Room();
        $name = 'Conference Room A';
        
        $result = $room->setRoomName($name);
        
        $this->assertSame($room, $result);
        $this->assertEquals($name, $room->getRoomName());
    }

    public function testSetAndGetCapacity(): void
    {
        $room = new Room();
        $capacity = 20;
        
        $result = $room->setCapacity($capacity);
        
        $this->assertSame($room, $result);
        $this->assertEquals($capacity, $room->getCapacity());
    }

    public function testSetAndGetSize(): void
    {
        $room = new Room();
        $size = 45.5;
        
        $result = $room->setSize($size);
        
        $this->assertSame($room, $result);
        $this->assertEquals($size, $room->getSize());
    }

    public function testSetAndGetLocation(): void
    {
        $room = new Room();
        $location = 'Building A, Floor 2';
        
        $result = $room->setLocation($location);
        
        $this->assertSame($room, $result);
        $this->assertEquals($location, $room->getLocation());
    }

    public function testSetAndGetAccess(): void
    {
        $room = new Room();
        $access = 'public';
        
        $result = $room->setAccess($access);
        
        $this->assertSame($room, $result);
        $this->assertEquals($access, $room->getAccess());
    }

    public function testSetAndGetDescription(): void
    {
        $room = new Room();
        $description = 'Large conference room with projector';
        
        $result = $room->setDescription($description);
        
        $this->assertSame($room, $result);
        $this->assertEquals($description, $room->getDescription());
    }

    public function testSetAndGetLighting(): void
    {
        $room = new Room();
        $lighting = 'natural';
        
        $result = $room->setLighting($lighting);
        
        $this->assertSame($room, $result);
        $this->assertEquals($lighting, $room->getLighting());
    }

    public function testSetAndGetAirConditioning(): void
    {
        $room = new Room();
        $airConditioning = ['type' => 'central', 'temperature' => 22];
        
        $result = $room->setAirConditioning($airConditioning);
        
        $this->assertSame($room, $result);
        $this->assertEquals($airConditioning, $room->getAirConditioning());
    }

    public function testSetAndGetImagePaths(): void
    {
        $room = new Room();
        $imagePaths = ['image1.jpg', 'image2.jpg'];
        
        $result = $room->setImagePaths($imagePaths);
        
        $this->assertSame($room, $result);
        $this->assertEquals($imagePaths, $room->getImagePaths());
    }

    public function testSetAndGetOrganization(): void
    {
        $room = new Room();
        $organization = $this->createMock(Organization::class);
        
        $result = $room->setOrganization($organization);
        
        $this->assertSame($room, $result);
        $this->assertSame($organization, $room->getOrganization());
    }

    public function testAddEquipment(): void
    {
        $room = new Room();
        $equipment = $this->createMock(Equipment::class);
        
        $equipment->expects($this->once())
            ->method('setRoom')
            ->with($room);
        
        $result = $room->addEquipment($equipment);
        
        $this->assertSame($room, $result);
        $this->assertCount(1, $room->getEquipment());
        $this->assertTrue($room->getEquipment()->contains($equipment));
    }

    public function testAddEquipmentDoesNotAddDuplicates(): void
    {
        $room = new Room();
        $equipment = $this->createMock(Equipment::class);
        
        $equipment->expects($this->once())
            ->method('setRoom')
            ->with($room);
        
        $room->addEquipment($equipment);
        $room->addEquipment($equipment);
        
        $this->assertCount(1, $room->getEquipment());
    }

    public function testRemoveEquipment(): void
    {
        $room = new Room();
        $equipment = $this->createMock(Equipment::class);
        
        $equipment->method('setRoom')->willReturn($equipment);
        
        $room->addEquipment($equipment);
        $this->assertCount(1, $room->getEquipment());
        
        $result = $room->removeEquipment($equipment);
        
        $this->assertSame($room, $result);
        $this->assertCount(0, $room->getEquipment());
    }

    public function testSetAndGetRoomStatus(): void
    {
        $room = new Room();
        $roomStatus = $this->createMock(RoomStatus::class);
        
        $roomStatus->expects($this->once())
            ->method('setRoom')
            ->with($room);
        
        $result = $room->setRoomStatus($roomStatus);
        
        $this->assertSame($room, $result);
        $this->assertSame($roomStatus, $room->getRoomStatus());
    }

    public function testAddFavoritedByUser(): void
    {
        $room = new Room();
        $user = $this->createMock(User::class);
        
        $user->expects($this->once())
            ->method('addFavoriteRoom')
            ->with($room);
        
        $result = $room->addFavoritedByUser($user);
        
        $this->assertSame($room, $result);
        $this->assertCount(1, $room->getFavoritedByUsers());
    }

    public function testRemoveFavoritedByUser(): void
    {
        $room = new Room();
        $user = $this->createMock(User::class);
        
        $user->method('addFavoriteRoom')->willReturn($user);
        $user->expects($this->once())
            ->method('removeFavoriteRoom')
            ->with($room);
        
        $room->addFavoritedByUser($user);
        $result = $room->removeFavoritedByUser($user);
        
        $this->assertSame($room, $result);
    }

    public function testAddBooking(): void
    {
        $room = new Room();
        $booking = $this->createMock(Booking::class);
        
        $booking->expects($this->once())
            ->method('setRoom')
            ->with($room);
        
        $result = $room->addBooking($booking);
        
        $this->assertSame($room, $result);
        $this->assertCount(1, $room->getBookings());
    }

    public function testFluentInterface(): void
    {
        $room = new Room();
        $organization = $this->createMock(Organization::class);
        
        $result = $room
            ->setRoomName('Test Room')
            ->setCapacity(10)
            ->setSize(25.5)
            ->setLocation('Floor 1')
            ->setAccess('private')
            ->setDescription('Test description')
            ->setLighting('artificial')
            ->setOrganization($organization);
        
        $this->assertSame($room, $result);
        $this->assertEquals('Test Room', $room->getRoomName());
        $this->assertEquals(10, $room->getCapacity());
        $this->assertEquals(25.5, $room->getSize());
        $this->assertEquals('Floor 1', $room->getLocation());
        $this->assertEquals('private', $room->getAccess());
        $this->assertEquals('Test description', $room->getDescription());
        $this->assertEquals('artificial', $room->getLighting());
        $this->assertSame($organization, $room->getOrganization());
    }
}
