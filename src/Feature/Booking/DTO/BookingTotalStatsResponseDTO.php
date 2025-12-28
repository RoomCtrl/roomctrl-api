<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

class BookingTotalStatsResponseDTO
{
    public function __construct(
        public readonly int $total,
        public readonly int $thisMonth,
        public readonly int $thisWeek,
        public readonly int $today
    ) {
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'thisMonth' => $this->thisMonth,
            'thisWeek' => $this->thisWeek,
            'today' => $this->today
        ];
    }
}
