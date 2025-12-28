<?php

declare(strict_types=1);

namespace App\Feature\Room\DTO;

class RoomUsageStatDTO
{
    public function __construct(
        public readonly string $roomId,
        public readonly string $roomName,
        public readonly int $count,
        public readonly float $percentage
    ) {
    }

    public function toArray(): array
    {
        return [
            'roomId' => $this->roomId,
            'roomName' => $this->roomName,
            'count' => $this->count,
            'percentage' => round($this->percentage, 1)
        ];
    }
}
