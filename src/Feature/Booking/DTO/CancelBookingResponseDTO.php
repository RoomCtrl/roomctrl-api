<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

use App\Feature\Booking\DTO\BookingResponseDTO;

class CancelBookingResponseDTO
{
    public string $message;
    public BookingResponseDTO $booking;

    public function __construct(string $message, BookingResponseDTO $booking)
    {
        $this->message = $message;
        $this->booking = $booking;
    }

    public function toArray(): array
    {
        return [
            'code' => 200,
            'message' => $this->message,
            'booking' => $this->booking->toArray()
        ];
    }
}
