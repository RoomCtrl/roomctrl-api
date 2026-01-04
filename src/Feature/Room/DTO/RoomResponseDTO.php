<?php

declare(strict_types=1);

namespace App\Feature\Room\DTO;

use App\Feature\Room\Entity\Room;

class RoomResponseDTO
{
    public string $roomId;
    public string $roomName;
    public string $status;
    public int $capacity;
    public float $size;
    public string $location;
    public string $access;
    public ?string $description;
    public ?string $lighting;
    public ?array $airConditioning;
    /** @var string[] */
    public array $imagePaths;
    /** @var array<int, array{name: string, category: string, quantity: int}> */
    public array $equipment;
    public ?array $currentBooking = null;
    /** @var array<int, array>|null */
    public ?array $nextBookings = null;

    private function __construct(
        string $roomId,
        string $roomName,
        string $status,
        int $capacity,
        float $size,
        string $location,
        string $access,
        ?string $description,
        ?string $lighting,
        ?array $airConditioning,
        array $imagePaths,
        array $equipment
    ) {
        $this->roomId = $roomId;
        $this->roomName = $roomName;
        $this->status = $status;
        $this->capacity = $capacity;
        $this->size = $size;
        $this->location = $location;
        $this->access = $access;
        $this->description = $description;
        $this->lighting = $lighting;
        $this->airConditioning = $airConditioning;
        $this->imagePaths = $imagePaths;
        $this->equipment = $equipment;
    }

    public static function fromEntity(Room $room): self
    {
        $equipment = [];
        foreach ($room->getEquipment() as $item) {
            $equipment[] = [
                'name' => $item->getName(),
                'category' => $item->getCategory(),
                'quantity' => $item->getQuantity()
            ];
        }

        return new self(
            $room->getId()->toRfc4122(),
            $room->getRoomName(),
            $room->getRoomStatus()?->getStatus() ?? 'unknown',
            $room->getCapacity(),
            $room->getSize(),
            $room->getLocation(),
            $room->getAccess(),
            $room->getDescription(),
            $room->getLighting(),
            $room->getAirConditioning(),
            $room->getImagePaths() ?? [],
            $equipment
        );
    }

    public function withBookings(?array $currentBooking, ?array $nextBookings): self
    {
        $this->currentBooking = $currentBooking;
        $this->nextBookings = $nextBookings;
        return $this;
    }

    public function toArray(): array
    {
        $data = [
            'roomId' => $this->roomId,
            'roomName' => $this->roomName,
            'status' => $this->status,
            'capacity' => $this->capacity,
            'size' => $this->size,
            'location' => $this->location,
            'access' => $this->access,
            'description' => $this->description,
            'lighting' => $this->lighting,
            'airConditioning' => $this->airConditioning,
            'imagePaths' => $this->imagePaths,
            'equipment' => $this->equipment
        ];

        if ($this->currentBooking !== null || $this->nextBookings !== null) {
            $data['currentBooking'] = $this->currentBooking;
            $data['nextBookings'] = $this->nextBookings;
        }

        return $data;
    }
}
