<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

readonly class BookingTotalStatsResponseDTO
{
    public function __construct(
        public int $total,
        public int $thisMonth,
        public int $thisWeek,
        public int $today
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
