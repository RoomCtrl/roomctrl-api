<?php

declare(strict_types=1);

namespace App\Tests\Feature\Room\Entity;

use App\Feature\Room\Entity\Equipment;
use App\Feature\Room\Entity\Room;
use PHPUnit\Framework\TestCase;

class EquipmentTest extends TestCase
{
    public function testEquipmentCreation(): void
    {
        $equipment = new Equipment();
        
        $this->assertNull($equipment->getId());
        $this->assertEquals(1, $equipment->getQuantity());
    }

    public function testSetAndGetName(): void
    {
        $equipment = new Equipment();
        $name = 'Projector';
        
        $result = $equipment->setName($name);
        
        $this->assertSame($equipment, $result);
        $this->assertEquals($name, $equipment->getName());
    }

    public function testSetAndGetCategory(): void
    {
        $equipment = new Equipment();
        $category = 'Electronics';
        
        $result = $equipment->setCategory($category);
        
        $this->assertSame($equipment, $result);
        $this->assertEquals($category, $equipment->getCategory());
    }

    public function testSetAndGetQuantity(): void
    {
        $equipment = new Equipment();
        $quantity = 5;
        
        $result = $equipment->setQuantity($quantity);
        
        $this->assertSame($equipment, $result);
        $this->assertEquals($quantity, $equipment->getQuantity());
    }

    public function testSetAndGetRoom(): void
    {
        $equipment = new Equipment();
        $room = $this->createMock(Room::class);
        
        $result = $equipment->setRoom($room);
        
        $this->assertSame($equipment, $result);
        $this->assertSame($room, $equipment->getRoom());
    }

    public function testDefaultQuantity(): void
    {
        $equipment = new Equipment();
        
        $this->assertEquals(1, $equipment->getQuantity());
    }

    public function testFluentInterface(): void
    {
        $equipment = new Equipment();
        $room = $this->createMock(Room::class);
        
        $result = $equipment
            ->setName('Whiteboard')
            ->setCategory('Furniture')
            ->setQuantity(2)
            ->setRoom($room);
        
        $this->assertSame($equipment, $result);
        $this->assertEquals('Whiteboard', $equipment->getName());
        $this->assertEquals('Furniture', $equipment->getCategory());
        $this->assertEquals(2, $equipment->getQuantity());
        $this->assertSame($room, $equipment->getRoom());
    }
}
