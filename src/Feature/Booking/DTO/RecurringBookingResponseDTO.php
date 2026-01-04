<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

class RecurringBookingResponseDTO
{
    public function __construct(
        public readonly int $createdCount,
        public readonly array $bookingIds
    ) {
    }

    public function toArray(): array
    {
        return [
            'createdCount' => $this->createdCount,
            'bookingIds' => $this->bookingIds,
            'message' => "Successfully created {$this->createdCount} recurring bookings"
        ];
    }
}
