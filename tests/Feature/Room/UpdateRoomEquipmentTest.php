<?php

declare(strict_types=1);

namespace App\Tests\Feature\Room;

use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Equipment;
use App\Feature\Room\Entity\Room;
use App\Feature\Room\Entity\RoomStatus;
use App\Feature\Room\DTO\UpdateRoomRequest;
use App\Feature\Room\Service\RoomService;
use App\Feature\Room\Repository\RoomRepository;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\User\Repository\UserRepository;
use PHPUnit\Framework\TestCase;

class UpdateRoomEquipmentTest extends TestCase
{
    private RoomService $roomService;
    private RoomRepository $roomRepository;

    protected function setUp(): void
    {
        $this->roomRepository = $this->createMock(RoomRepository::class);
        $bookingRepository = $this->createMock(BookingRepository::class);
        $userRepository = $this->createMock(UserRepository::class);

        $this->roomService = new RoomService(
            $this->roomRepository,
            $bookingRepository,
            $userRepository
        );
    }

    public function testUpdateRoomWithEquipment(): void
    {
        $organization = new Organization();
        $organization->setRegon('123456789');
        $organization->setName('Test Org');
        $organization->setEmail('test@org.com');

        $room = new Room();
        $room->setRoomName('Test Room');
        $room->setCapacity(20);
        $room->setSize(45.5);
        $room->setLocation('Building A');
        $room->setAccess('public');
        $room->setOrganization($organization);

        $roomStatus = new RoomStatus();
        $roomStatus->setStatus('available');
        $roomStatus->setRoom($room);
        $room->setRoomStatus($roomStatus);

        $initialEquipment = new Equipment();
        $initialEquipment->setName('Old Projector');
        $initialEquipment->setCategory('video');
        $initialEquipment->setQuantity(1);
        $initialEquipment->setRoom($room);
        $room->addEquipment($initialEquipment);

        $this->assertCount(1, $room->getEquipment());

        $updateRequest = new UpdateRoomRequest();
        $updateRequest->equipment = [
            [
                'name' => 'New 4K Projector',
                'category' => 'video',
                'quantity' => 1
            ],
            [
                'name' => 'Office Chairs',
                'category' => 'furniture',
                'quantity' => 20
            ]
        ];

        $this->roomRepository->expects($this->once())
            ->method('flush');

        $this->roomService->updateRoom($room, $updateRequest);

        $equipment = $room->getEquipment();
        $this->assertCount(2, $equipment);

        $equipmentItems = [];
        foreach ($equipment as $item) {
            $equipmentItems[] = $item;
        }
        
        $this->assertCount(2, $equipmentItems);
        $this->assertEquals('New 4K Projector', $equipmentItems[0]->getName());
        $this->assertEquals('video', $equipmentItems[0]->getCategory());
        $this->assertEquals(1, $equipmentItems[0]->getQuantity());

        $this->assertEquals('Office Chairs', $equipmentItems[1]->getName());
        $this->assertEquals('furniture', $equipmentItems[1]->getCategory());
        $this->assertEquals(20, $equipmentItems[1]->getQuantity());
    }

    public function testUpdateRoomWithoutEquipment(): void
    {
        $organization = new Organization();
        $organization->setRegon('123456789');
        $organization->setName('Test Org');
        $organization->setEmail('test@org.com');

        $room = new Room();
        $room->setRoomName('Test Room');
        $room->setCapacity(20);
        $room->setSize(45.5);
        $room->setLocation('Building A');
        $room->setAccess('public');
        $room->setOrganization($organization);

        $equipment = new Equipment();
        $equipment->setName('Projector');
        $equipment->setCategory('video');
        $equipment->setQuantity(1);
        $equipment->setRoom($room);
        $room->addEquipment($equipment);

        $this->assertCount(1, $room->getEquipment());

        $updateRequest = new UpdateRoomRequest();
        $updateRequest->roomName = 'Updated Room Name';

        $this->roomRepository->expects($this->once())
            ->method('flush');

        $this->roomService->updateRoom($room, $updateRequest);

        $this->assertCount(1, $room->getEquipment());
        $this->assertEquals('Updated Room Name', $room->getRoomName());
    }

    public function testUpdateRoomRemoveAllEquipment(): void
    {
        $organization = new Organization();
        $organization->setRegon('123456789');
        $organization->setName('Test Org');
        $organization->setEmail('test@org.com');

        $room = new Room();
        $room->setRoomName('Test Room');
        $room->setCapacity(20);
        $room->setSize(45.5);
        $room->setLocation('Building A');
        $room->setAccess('public');
        $room->setOrganization($organization);

        $equipment = new Equipment();
        $equipment->setName('Projector');
        $equipment->setCategory('video');
        $equipment->setQuantity(1);
        $equipment->setRoom($room);
        $room->addEquipment($equipment);

        $this->assertCount(1, $room->getEquipment());

        $updateRequest = new UpdateRoomRequest();
        $updateRequest->equipment = [];

        $this->roomRepository->expects($this->once())
            ->method('flush');

        $this->roomService->updateRoom($room, $updateRequest);

        $this->assertCount(0, $room->getEquipment());
    }
}
