<?php

declare(strict_types=1);

namespace App\Feature\Room\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Equipment;
use App\Feature\Room\Entity\Room;
use App\Feature\Room\Entity\RoomStatus;
use App\Feature\Room\Repository\RoomRepository;
use App\Feature\User\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

class RoomService
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RoomSerializer $roomSerializer
    ) {
    }

    /**
     * Get all rooms, optionally filtered by status
     * 
     * @return Room[]
     */
    public function getAllRooms(?string $status = null): array
    {
        if ($status) {
            return $this->roomRepository->findByStatus($status);
        }

        return $this->roomRepository->findAll();
    }

    /**
     * Get room by ID
     */
    public function getRoomById(Uuid $id): ?Room
    {
        return $this->roomRepository->findById($id);
    }

    /**
     * Toggle favorite status for a room
     */
    public function toggleFavorite(Room $room, User $user): bool
    {
        $isFavorite = $user->isFavoriteRoom($room);

        if ($isFavorite) {
            $user->removeFavoriteRoom($room);
        } else {
            $user->addFavoriteRoom($room);
        }

        $this->entityManager->flush();

        return !$isFavorite;
    }

    /**
     * Get user's favorite rooms
     * 
     * @return Room[]
     */
    public function getFavoriteRooms(User $user): array
    {
        return $user->getFavoriteRooms()->toArray();
    }

    /**
     * Get recently booked rooms by user with booking data
     * 
     * @return array<int, array{room: Room, lastBooking: ?Booking}>
     */
    public function getRecentlyBookedRooms(User $user, int $limit = 3): array
    {
        $bookings = $this->entityManager->getRepository(Booking::class)
            ->createQueryBuilder('b')
            ->where('b.user = :user')
            ->andWhere('b.room IS NOT NULL')
            ->setParameter('user', $user)
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit * 2)
            ->getQuery()
            ->getResult();

        $result = [];
        $processedRoomIds = [];

        foreach ($bookings as $booking) {
            $room = $booking->getRoom();
            
            if ($room && !in_array($room->getId()->toRfc4122(), $processedRoomIds)) {
                $result[] = [
                    'room' => $room,
                    'lastBooking' => $booking
                ];
                
                $processedRoomIds[] = $room->getId()->toRfc4122();
                
                if (count($result) >= $limit) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Get current and next bookings for a room
     */
    public function getRoomBookings(Room $room): array
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
                $currentBooking = $booking;
            } elseif ($booking->getStartedAt() > $now) {
                $nextBookings[] = $booking;
            }
        }

        return [
            'current' => $currentBooking,
            'next' => $nextBookings
        ];
    }

    /**
     * Serialize room data
     */
    public function serializeRoom(Room $room, bool $withBookings = false): array
    {
        return $this->roomSerializer->serialize($room, $withBookings);
    }

    /**
     * Serialize multiple rooms
     * 
     * @param Room[] $rooms
     */
    public function serializeRooms(array $rooms, bool $withBookings = false): array
    {
        return $this->roomSerializer->serializeMany($rooms, $withBookings);
    }

    /**
     * Create a new room
     */
    public function createRoom(
        string $roomName,
        int $capacity,
        float $size,
        string $location,
        string $access,
        Organization $organization,
        ?string $status = 'available',
        ?string $description = null,
        ?string $lighting = null,
        ?array $airConditioning = null,
        array $equipment = []
    ): Room {
        $room = new Room();
        $room->setRoomName($roomName);
        $room->setCapacity($capacity);
        $room->setSize($size);
        $room->setLocation($location);
        $room->setAccess($access);
        $room->setOrganization($organization);
        
        $roomStatus = new RoomStatus();
        $roomStatus->setStatus($status);
        $roomStatus->setRoom($room);
        $room->setRoomStatus($roomStatus);

        if ($description !== null) {
            $room->setDescription($description);
        }
        if ($lighting !== null) {
            $room->setLighting($lighting);
        }
        if ($airConditioning !== null) {
            $room->setAirConditioning($airConditioning);
        }

        foreach ($equipment as $equipData) {
            $equipmentItem = new Equipment();
            $equipmentItem->setName($equipData['name']);
            $equipmentItem->setCategory($equipData['category']);
            $equipmentItem->setQuantity($equipData['quantity'] ?? 1);
            $room->addEquipment($equipmentItem);
        }

        $this->entityManager->persist($room);
        $this->entityManager->flush();

        return $room;
    }

    /**
     * Update an existing room
     */
    public function updateRoom(
        Room $room,
        ?string $roomName = null,
        ?int $capacity = null,
        ?float $size = null,
        ?string $location = null,
        ?string $access = null,
        ?string $status = null,
        ?string $description = null,
        ?string $lighting = null,
        ?array $airConditioning = null
    ): Room {
        if ($roomName !== null) {
            $room->setRoomName($roomName);
        }
        if ($status !== null) {
            if (!$room->getRoomStatus()) {
                $roomStatus = new RoomStatus();
                $roomStatus->setRoom($room);
                $room->setRoomStatus($roomStatus);
            }
            $room->getRoomStatus()->setStatus($status);
        }
        if ($capacity !== null) {
            $room->setCapacity($capacity);
        }
        if ($size !== null) {
            $room->setSize($size);
        }
        if ($location !== null) {
            $room->setLocation($location);
        }
        if ($access !== null) {
            $room->setAccess($access);
        }
        if ($description !== null) {
            $room->setDescription($description);
        }
        if ($lighting !== null) {
            $room->setLighting($lighting);
        }
        if ($airConditioning !== null) {
            $room->setAirConditioning($airConditioning);
        }

        $this->entityManager->flush();

        return $room;
    }

    /**
     * Delete a room and cancel all active bookings
     */
    public function deleteRoom(Room $room): void
    {
        $now = new DateTimeImmutable();
        $activeBookings = $this->entityManager->getRepository(Booking::class)
            ->createQueryBuilder('b')
            ->where('b.room = :room')
            ->andWhere('b.status = :status')
            ->andWhere('b.endedAt >= :now')
            ->setParameter('room', $room)
            ->setParameter('status', 'active')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        foreach ($activeBookings as $booking) {
            $booking->setStatus('cancelled');
        }
        
        $this->entityManager->flush();
        
        $this->entityManager->remove($room);
        $this->entityManager->flush();
    }
}
