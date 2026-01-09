<?php

declare(strict_types=1);

namespace App\Feature\Room\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\DTO\CreateRoomRequest;
use App\Feature\Room\DTO\RoomIssueStatDTO;
use App\Feature\Room\DTO\RoomResponseDTO;
use App\Feature\Room\DTO\RoomUsageStatDTO;
use App\Feature\Room\DTO\UpdateRoomRequest;
use App\Feature\Room\Entity\Equipment;
use App\Feature\Room\Entity\Room;
use App\Feature\Room\Entity\RoomStatus;
use App\Feature\Room\Repository\RoomRepository;
use App\Feature\User\Entity\User;
use App\Feature\User\Repository\UserRepository;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

class RoomService implements RoomServiceInterface
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly BookingRepository $bookingRepository,
        private readonly UserRepository $userRepository
    ) {
    }

    public function getAllRooms(?string $status = null, ?Organization $organization = null): array
    {
        if ($organization) {
            if ($status) {
                return $this->roomRepository->findByOrganizationAndStatus($organization, $status);
            }
            return $this->roomRepository->findBy(['organization' => $organization]);
        }

        if ($status) {
            return $this->roomRepository->findByStatus($status);
        }

        return $this->roomRepository->findAll();
    }

    public function getRoomById(Uuid $id): ?Room
    {
        return $this->roomRepository->findById($id);
    }

    public function toggleFavorite(Room $room, User $user): bool
    {
        $isFavorite = $user->isFavoriteRoom($room);

        if ($isFavorite) {
            $user->removeFavoriteRoom($room);
        } else {
            $user->addFavoriteRoom($room);
        }

        $this->userRepository->flush();

        return !$isFavorite;
    }

    public function getFavoriteRooms(User $user): array
    {
        return $user->getFavoriteRooms()->toArray();
    }

    public function getRecentlyBookedRooms(User $user, int $limit = 3): array
    {
        $bookings = $this->bookingRepository->createQueryBuilder('b')
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

    public function getRoomResponse(Room $room, bool $withBookings = false): RoomResponseDTO
    {
        $dto = RoomResponseDTO::fromEntity($room);

        if ($withBookings) {
            $bookingData = $this->getBookingData($room);
            $dto->withBookings($bookingData['current'], $bookingData['next']);
        }

        return $dto;
    }

    public function getRoomResponses(array $rooms, bool $withBookings = false): array
    {
        return array_map(
            fn(Room $room) => $this->getRoomResponse($room, $withBookings),
            $rooms
        );
    }

    public function createRoom(CreateRoomRequest $dto, Organization $organization): Room
    {
        $room = new Room();
        $room->setRoomName($dto->roomName);
        $room->setCapacity($dto->capacity);
        $room->setSize($dto->size);
        $room->setLocation($dto->location);
        $room->setAccess($dto->access);
        $room->setOrganization($organization);

        $roomStatus = new RoomStatus();
        $roomStatus->setStatus('available');
        $roomStatus->setRoom($room);
        $room->setRoomStatus($roomStatus);

        if ($dto->description !== null) {
            $room->setDescription($dto->description);
        }
        if ($dto->lighting !== null) {
            $room->setLighting($dto->lighting);
        }
        if ($dto->airConditioning !== null) {
            $room->setAirConditioning($dto->airConditioning);
        }

        if (!empty($dto->equipment)) {
            foreach ($dto->equipment as $equipData) {
                $equipmentItem = new Equipment();
                $equipmentItem->setName($equipData['name']);
                $equipmentItem->setCategory($equipData['category']);
                $equipmentItem->setQuantity($equipData['quantity'] ?? 1);
                $room->addEquipment($equipmentItem);
            }
        }

        $this->roomRepository->save($room, true);

        return $room;
    }

    public function updateRoom(Room $room, UpdateRoomRequest $dto): void
    {
        if ($dto->roomName !== null) {
            $room->setRoomName($dto->roomName);
        }
        if ($dto->capacity !== null) {
            $room->setCapacity($dto->capacity);
        }
        if ($dto->size !== null) {
            $room->setSize($dto->size);
        }
        if ($dto->location !== null) {
            $room->setLocation($dto->location);
        }
        if ($dto->access !== null) {
            $room->setAccess($dto->access);
        }
        if ($dto->description !== null) {
            $room->setDescription($dto->description);
        }
        if ($dto->lighting !== null) {
            $room->setLighting($dto->lighting);
        }
        if ($dto->airConditioning !== null) {
            $room->setAirConditioning($dto->airConditioning);
        }

        if ($dto->equipment !== null) {
            // Remove existing equipment
            foreach ($room->getEquipment() as $existingEquipment) {
                $room->removeEquipment($existingEquipment);
            }

            // Add new equipment
            foreach ($dto->equipment as $equipData) {
                $equipmentItem = new Equipment();
                $equipmentItem->setName($equipData['name']);
                $equipmentItem->setCategory($equipData['category']);
                $equipmentItem->setQuantity($equipData['quantity'] ?? 1);
                $room->addEquipment($equipmentItem);
            }
        }

        $this->roomRepository->flush();
    }

    public function deleteRoom(Room $room): void
    {
        $now = new DateTimeImmutable();
        $activeBookings = $this->bookingRepository->createQueryBuilder('b')
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
            $this->bookingRepository->save($booking, false);
        }

        $this->bookingRepository->flush();
        $this->roomRepository->remove($room, true);
    }

    private function getBookingData(Room $room): array
    {
        $now = new DateTimeImmutable();
        $bookings = $this->bookingRepository->findBy(
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

    private function serializeBooking(Booking $booking): array
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

    public function getMostUsedRooms(Organization $organization, int $limit = 5): array
    {
        $rawResults = $this->roomRepository->getMostUsedRooms($organization, $limit);

        $totalBookings = array_sum(array_column($rawResults, 'bookingCount'));

        return array_map(function (array $item) use ($totalBookings): RoomUsageStatDTO {
            $percentage = $totalBookings > 0 ? round(($item['bookingCount'] / $totalBookings) * 100, 2) : 0;

            return new RoomUsageStatDTO(
                roomId: $item['id'],
                roomName: $item['roomName'],
                count: (int) $item['bookingCount'],
                percentage: $percentage,
                weeklyBookings: (int) $item['weeklyBookings'],
                monthlyBookings: (int) $item['monthlyBookings']
            );
        }, $rawResults);
    }

    public function getLeastUsedRooms(Organization $organization, int $limit = 5): array
    {
        $rawResults = $this->roomRepository->getLeastUsedRooms($organization, $limit);

        $totalBookings = array_sum(array_column($rawResults, 'bookingCount'));

        return array_map(function (array $item) use ($totalBookings): RoomUsageStatDTO {
            $percentage = $totalBookings > 0 ? round(($item['bookingCount'] / $totalBookings) * 100, 2) : 0;

            return new RoomUsageStatDTO(
                roomId: $item['id'],
                roomName: $item['roomName'],
                count: (int) $item['bookingCount'],
                percentage: $percentage,
                weeklyBookings: (int) $item['weeklyBookings'],
                monthlyBookings: (int) $item['monthlyBookings']
            );
        }, $rawResults);
    }

    public function getRoomsWithMostIssues(Organization $organization, int $limit = 5): array
    {
        $rawResults = $this->roomRepository->getRoomsWithMostIssues($organization, $limit);

        return array_map(function (array $item): RoomIssueStatDTO {
            $priority = match (true) {
                $item['issueCount'] >= 10 => 'high',
                $item['issueCount'] >= 5 => 'medium',
                default => 'low'
            };

            return new RoomIssueStatDTO(
                roomId: $item['id'],
                roomName: $item['roomName'],
                issueCount: (int) $item['issueCount'],
                priority: $priority
            );
        }, $rawResults);
    }

    public function canUserAccessRoom(Room $room, User $user): bool
    {
        return $room->getOrganization()->getId()->toRfc4122() === $user->getOrganization()->getId()->toRfc4122();
    }

    public function getImagePaths(Room $room): array
    {
        return $room->getImagePaths() ?? [];
    }

    public function setImagePaths(Room $room, array $paths): void
    {
        $room->setImagePaths($paths);
        $this->roomRepository->flush();
    }

    public function addImagePaths(Room $room, array $newPaths): void
    {
        $existingPaths = $room->getImagePaths() ?? [];
        $allPaths = array_merge($existingPaths, $newPaths);
        $room->setImagePaths($allPaths);
        $this->roomRepository->flush();
    }

    public function removeImagePath(Room $room, int $index): ?string
    {
        $imagePaths = $room->getImagePaths() ?? [];

        if (!isset($imagePaths[$index])) {
            return null;
        }

        $deletedPath = $imagePaths[$index];
        unset($imagePaths[$index]);
        $imagePaths = array_values($imagePaths);

        $room->setImagePaths($imagePaths);
        $this->roomRepository->flush();

        return $deletedPath;
    }

    public function clearAllImages(Room $room): int
    {
        $imagePaths = $room->getImagePaths() ?? [];
        $count = count($imagePaths);

        $room->setImagePaths([]);
        $this->roomRepository->flush();

        return $count;
    }
}
