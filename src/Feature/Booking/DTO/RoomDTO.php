<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

class RoomDTO
{
    public string $id;
    public string $roomName;
    public string $location;

    public function __construct(string $id, string $roomName, string $location)
    {
        $this->id = $id;
        $this->roomName = $roomName;
        $this->location = $location;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'roomName' => $this->roomName,
            'location' => $this->location
        ];
    }
}
