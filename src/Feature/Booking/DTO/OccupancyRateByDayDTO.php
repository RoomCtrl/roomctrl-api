<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

class OccupancyRateByDayDTO
{
    public function __construct(
        public readonly string $dayOfWeek,
        public readonly float $occupancyRate
    ) {
    }

    public function toArray(): array
    {
        return [
            'dayOfWeek' => $this->dayOfWeek,
            'occupancyRate' => round($this->occupancyRate, 1)
        ];
    }
}
