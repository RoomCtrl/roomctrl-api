<?php

declare(strict_types=1);

namespace App\Tests\Feature\Booking\DTO;

use App\Feature\Booking\DTO\RoomDTO;
use PHPUnit\Framework\TestCase;

class RoomDTOTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $id = '123e4567-e89b-12d3-a456-426614174000';
        $roomName = 'Conference Room A';
        $location = 'Building 1, Floor 2';
        
        $dto = new RoomDTO($id, $roomName, $location);
        
        $this->assertEquals($id, $dto->id);
        $this->assertEquals($roomName, $dto->roomName);
        $this->assertEquals($location, $dto->location);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $id = '123e4567-e89b-12d3-a456-426614174000';
        $roomName = 'Conference Room A';
        $location = 'Building 1, Floor 2';
        
        $dto = new RoomDTO($id, $roomName, $location);
        $array = $dto->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('roomName', $array);
        $this->assertArrayHasKey('location', $array);
        $this->assertEquals($id, $array['id']);
        $this->assertEquals($roomName, $array['roomName']);
        $this->assertEquals($location, $array['location']);
    }

    public function testToArrayContainsAllProperties(): void
    {
        $dto = new RoomDTO(
            '123e4567-e89b-12d3-a456-426614174000',
            'Meeting Room',
            'Floor 3'
        );
        
        $array = $dto->toArray();
        
        $this->assertCount(3, $array);
    }

    public function testWithEmptyStrings(): void
    {
        $dto = new RoomDTO('', '', '');
        
        $this->assertEquals('', $dto->id);
        $this->assertEquals('', $dto->roomName);
        $this->assertEquals('', $dto->location);
        
        $array = $dto->toArray();
        $this->assertEquals('', $array['id']);
        $this->assertEquals('', $array['roomName']);
        $this->assertEquals('', $array['location']);
    }

    public function testWithSpecialCharacters(): void
    {
        $id = 'test-id-äöü';
        $roomName = 'Salle de Réunion № 1';
        $location = 'Étage 2 & Bureau';
        
        $dto = new RoomDTO($id, $roomName, $location);
        
        $this->assertEquals($id, $dto->id);
        $this->assertEquals($roomName, $dto->roomName);
        $this->assertEquals($location, $dto->location);
    }
}
