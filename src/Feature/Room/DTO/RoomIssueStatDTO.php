<?php

declare(strict_types=1);

namespace App\Feature\Room\DTO;

class RoomIssueStatDTO
{
    public function __construct(
        public readonly string $roomId,
        public readonly string $roomName,
        public readonly int $issueCount,
        public readonly string $priority
    ) {
    }

    public function toArray(): array
    {
        return [
            'roomId' => $this->roomId,
            'roomName' => $this->roomName,
            'issueCount' => $this->issueCount,
            'priority' => $this->priority
        ];
    }
}
