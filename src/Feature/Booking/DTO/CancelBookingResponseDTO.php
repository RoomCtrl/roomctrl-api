<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

use Symfony\Component\HttpFoundation\Response;

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
            'code' => Response::HTTP_OK,
            'message' => $this->message,
            'booking' => $this->booking->toArray()
        ];
    }
}
