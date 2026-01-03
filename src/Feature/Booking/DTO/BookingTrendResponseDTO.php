<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

readonly class BookingTrendResponseDTO
{
    public function __construct(
        /** @var array<string, int> */
        public array $confirmed,
        /** @var array<string, int> */
        public array $pending,
        /** @var array<string, int> */
        public array $cancelled
    ) {
    }
}
