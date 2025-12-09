<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

class BookingCountsResponseDTO
{
    public int $count;
    public int $active;
    public int $completed;
    public int $cancelled;

    public function __construct(int $count, int $active, int $completed, int $cancelled)
    {
        $this->count = $count;
        $this->active = $active;
        $this->completed = $completed;
        $this->cancelled = $cancelled;
    }

    public function toArray(): array
    {
        return [
            'count' => $this->count,
            'active' => $this->active,
            'completed' => $this->completed,
            'cancelled' => $this->cancelled
        ];
    }
}
