<?php

declare(strict_types=1);

namespace App\Feature\Room\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Room\Entity\Equipment;
use App\Feature\Room\Entity\Room;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class RoomSerializer
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Serialize a single room
     */
    public function serialize(Room $room, bool $withBookings = false): array
    {
        $data = [
            'roomId' => $room->getId()->toRfc4122(),
            'roomName' => $room->getRoomName(),
            'status' => $room->getRoomStatus()?->getStatus() ?? 'unknown',
            'capacity' => $room->getCapacity(),
            'size' => $room->getSize(),
            'location' => $room->getLocation(),
            'access' => $room->getAccess(),
            'description' => $room->getDescription(),
            'lighting' => $room->getLighting(),
            'airConditioning' => $room->getAirConditioning(),
            'imagePaths' => $room->getImagePaths() ?? [],
            'equipment' => $this->serializeEquipment($room->getEquipment()->toArray())
        ];

        if ($withBookings) {
            $bookingData = $this->getBookingData($room);
            $data['currentBooking'] = $bookingData['current'];
            $data['nextBookings'] = $bookingData['next'];
        }

        return $data;
    }

    /**
     * Serialize multiple rooms
     * 
     * @param Room[] $rooms
     * @return array
     */
    public function serializeMany(array $rooms, bool $withBookings = false): array
    {
        return array_map(
            fn(Room $room) => $this->serialize($room, $withBookings),
            $rooms
        );
    }

    /**
     * Serialize room with last booking info
     */
    public function serializeWithLastBooking(Room $room, ?Booking $lastBooking = null): array
    {
        $data = $this->serialize($room, false);
        
        if ($lastBooking) {
            $data['lastBooking'] = $this->serializeBooking($lastBooking);
        }
        
        return $data;
    }

    /**
     * Serialize equipment collection
     * 
     * @param Equipment[] $equipment
     * @return array
     */
    private function serializeEquipment(array $equipment): array
    {
        return array_map(function (Equipment $item) {
            return [
                'name' => $item->getName(),
                'category' => $item->getCategory(),
                'quantity' => $item->getQuantity()
            ];
        }, $equipment);
    }

    /**
     * Serialize a booking
     */
    public function serializeBooking(Booking $booking): array
    {
        return [
            'id' => $booking->getId()->toRfc4122(),
            'title' => $booking->getTitle(),
            'startedAt' => $booking->getStartedAt()->format('c'),
            'endedAt' => $booking->getEndedAt()->format('c'),
            'participants' => $booking->getParticipantsCount(),
            'isPrivate' => $booking->isPrivate()
        ];
    }

    /**
     * Get current and next bookings for a room
     */
    private function getBookingData(Room $room): array
    {
        $now = new DateTimeImmutable();
        $bookings = $this->entityManager->getRepository(Booking::class)->findBy(
            ['room' => $room, 'status' => 'active'],
            ['startedAt' => 'ASC']
        );

        $currentBooking = null;
        $nextBookings = [];

        foreach ($bookings as $booking) {
            if ($booking->getStartedAt() <= $now && $booking->getEndedAt() > $now) {
                $currentBooking = $this->serializeBooking($booking);
            } elseif ($booking->getStartedAt() > $now) {
                $nextBookings[] = $this->serializeBooking($booking);
            }
        }

        return [
            'current' => $currentBooking,
            'next' => $nextBookings
        ];
    }
}
