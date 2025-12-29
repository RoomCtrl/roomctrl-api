<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

class BookingTrendResponseDTO
{
    public function __construct(
        /** @var array<string, int> */
        public readonly array $confirmed,
        /** @var array<string, int> */
        public readonly array $pending,
        /** @var array<string, int> */
        public readonly array $cancelled
    ) {}
}
