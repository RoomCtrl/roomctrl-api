<?php

declare(strict_types=1);

namespace App\Feature\Booking\Service;

use App\Feature\Booking\Entity\Booking;

class BookingSerializer
{
    public function serialize(Booking $booking): array
    {
        $room = $booking->getRoom();
        $roomData = null;
        
        if ($room) {
            $roomData = [
                'id' => $room->getId()->toRfc4122(),
                'roomName' => $room->getRoomName(),
                'location' => $room->getLocation()
            ];
        }

        $participants = [];
        foreach ($booking->getParticipants() as $participant) {
            $participants[] = [
                'id' => $participant->getId()->toRfc4122(),
                'username' => $participant->getUsername(),
                'firstName' => $participant->getFirstName(),
                'lastName' => $participant->getLastName(),
                'email' => $participant->getEmail()
            ];
        }

        return [
            'id' => $booking->getId()->toRfc4122(),
            'title' => $booking->getTitle(),
            'startedAt' => $booking->getStartedAt()->format('c'),
            'endedAt' => $booking->getEndedAt()->format('c'),
            'participantsCount' => $booking->getParticipantsCount(),
            'participants' => $participants,
            'isPrivate' => $booking->isPrivate(),
            'status' => $booking->getStatus(),
            'room' => $roomData,
            'user' => [
                'id' => $booking->getUser()->getId()->toRfc4122(),
                'username' => $booking->getUser()->getUsername(),
                'firstName' => $booking->getUser()->getFirstName(),
                'lastName' => $booking->getUser()->getLastName()
            ],
            'createdAt' => $booking->getCreatedAt()->format('c')
        ];
    }

    public function serializeMany(array $bookings): array
    {
        return array_map(fn(Booking $booking) => $this->serialize($booking), $bookings);
    }
}
