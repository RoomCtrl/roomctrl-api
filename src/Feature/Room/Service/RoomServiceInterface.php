<?php

declare(strict_types=1);

namespace App\Feature\Room\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\DTO\CreateRoomRequest;
use App\Feature\Room\DTO\RoomIssueStatDTO;
use App\Feature\Room\DTO\RoomResponseDTO;
use App\Feature\Room\DTO\RoomUsageStatDTO;
use App\Feature\Room\DTO\UpdateRoomRequest;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use Symfony\Component\Uid\Uuid;

interface RoomServiceInterface
{
    /**
     * Get all rooms, optionally filtered by status and organization
     *
     * @return array<int, Room>
     */
    public function getAllRooms(?string $status = null, ?Organization $organization = null): array;

    /**
     * Get room by ID
     */
    public function getRoomById(Uuid $id): ?Room;

    /**
     * Toggle favorite status for a room
     */
    public function toggleFavorite(Room $room, User $user): bool;

    /**
     * Get user's favorite rooms
     *
     * @return array<int, Room>
     */
    public function getFavoriteRooms(User $user): array;

    /**
     * Get recently booked rooms by user with booking data
     *
     * @return array<int, array{room: Room, lastBooking: ?Booking}>
     */
    public function getRecentlyBookedRooms(User $user, int $limit = 3): array;

    /**
     * Get room response DTO
     */
    public function getRoomResponse(Room $room, bool $withBookings = false): RoomResponseDTO;

    /**
     * Get room responses for multiple rooms
     *
     * @param array<int, Room> $rooms
     * @return array<int, RoomResponseDTO>
     */
    public function getRoomResponses(array $rooms, bool $withBookings = false): array;

    /**
     * Create a new room
     */
    public function createRoom(CreateRoomRequest $dto, Organization $organization): Room;

    /**
     * Update an existing room
     */
    public function updateRoom(Room $room, UpdateRoomRequest $dto): void;

    /**
     * Delete a room and cancel all active bookings
     */
    public function deleteRoom(Room $room): void;

    /**
     * Get most used rooms statistics
     *
     * @return array<int, RoomUsageStatDTO>
     */
    public function getMostUsedRooms(Organization $organization, int $limit = 5): array;

    /**
     * Get least used rooms statistics
     *
     * @return array<int, RoomUsageStatDTO>
     */
    public function getLeastUsedRooms(Organization $organization, int $limit = 5): array;

    /**
     * Get rooms with most issues statistics
     *
     * @return array<int, RoomIssueStatDTO>
     */
    public function getRoomsWithMostIssues(Organization $organization, int $limit = 5): array;

    /**
     * Check if user can access room (belongs to same organization)
     */
    public function canUserAccessRoom(Room $room, User $user): bool;

    /**
     * Get room image paths
     *
     * @return array<int, string>
     */
    public function getImagePaths(Room $room): array;

    /**
     * Set room image paths
     *
     * @param array<int, string> $paths
     */
    public function setImagePaths(Room $room, array $paths): void;

    /**
     * Add new image paths to room
     *
     * @param array<int, string> $newPaths
     */
    public function addImagePaths(Room $room, array $newPaths): void;

    /**
     * Remove image path by index
     *
     * @return string|null Removed path or null if not found
     */
    public function removeImagePath(Room $room, int $index): ?string;

    /**
     * Clear all room images
     *
     * @return int Number of images cleared
     */
    public function clearAllImages(Room $room): int;
}
