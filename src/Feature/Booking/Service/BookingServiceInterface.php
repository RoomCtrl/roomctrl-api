<?php

declare(strict_types=1);

namespace App\Feature\Booking\Service;

use App\Feature\Booking\DTO\BookingCountsResponseDTO;
use App\Feature\Booking\DTO\BookingTotalStatsResponseDTO;
use App\Feature\Booking\DTO\BookingTrendResponseDTO;
use App\Feature\Booking\DTO\CancelBookingResponseDTO;
use App\Feature\Booking\DTO\CreateBookingDTO;
use App\Feature\Booking\DTO\OccupancyRateByDayDTO;
use App\Feature\Booking\DTO\RecurringBookingResponseDTO;
use App\Feature\Booking\DTO\UpdateBookingDTO;
use App\Feature\Booking\Entity\Booking;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface BookingServiceInterface
{
    public function createBooking(
        string $title,
        Room $room,
        User $user,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
        int $participantsCount,
        bool $isPrivate = false,
        array $participantIds = []
    ): Booking;

    public function updateBooking(
        Booking $booking,
        ?string $title = null,
        ?Room $room = null,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $endedAt = null,
        ?int $participantsCount = null,
        ?bool $isPrivate = null,
        ?array $participantIds = null
    ): Booking;

    public function cancelBooking(Booking $booking): void;

    public function findConflictingBooking(
        Room $room,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
        ?Uuid $excludeBookingId = null
    ): ?Booking;

    public function canUserCancelBooking(Booking $booking, User $user): bool;

    public function canUserEditBooking(Booking $booking, User $user): bool;

    public function handleCreateBooking(CreateBookingDTO $dto, User $user): Booking;

    public function handleUpdateBooking(Booking $booking, UpdateBookingDTO $dto): Booking;

    public function handleCancelBooking(Booking $booking): CancelBookingResponseDTO;

    public function getBookingCounts(User $user): BookingCountsResponseDTO;

    public function getBookingCountsByOrganization(Organization $organization): BookingCountsResponseDTO;

    public function getTotalBookingStats(Organization $organization): BookingTotalStatsResponseDTO;

    /**
     * @return OccupancyRateByDayDTO[]
     */
    public function getOccupancyRateByDayOfWeek(Organization $organization): array;

    public function getBookingsList(?User $user = null, bool $myBookings = false): array;

    public function getBookingById(string $id): Booking;

    public function getBookingTrend(Organization $organization): BookingTrendResponseDTO;

    public function createRecurringBooking(
        Room $room,
        User $user,
        string $type,
        string $startTime,
        string $endTime,
        array $daysOfWeek,
        int $weeksAhead = 12
    ): RecurringBookingResponseDTO;
}
