<?php

declare(strict_types=1);

namespace App\Feature\Booking\Service;

use App\Feature\Booking\DTO\BookingCountsResponseDTO;
use App\Feature\Booking\DTO\BookingResponseDTO;
use App\Feature\Booking\DTO\BookingTotalStatsResponseDTO;
use App\Feature\Booking\DTO\BookingTrendResponseDTO;
use App\Feature\Booking\DTO\CancelBookingResponseDTO;
use App\Feature\Booking\DTO\CreateBookingDTO;
use App\Feature\Booking\DTO\OccupancyRateByDayDTO;
use App\Feature\Booking\DTO\RecurringBookingResponseDTO;
use App\Feature\Booking\DTO\UpdateBookingDTO;
use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Mail\Service\MailService;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use App\Feature\User\Repository\UserRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

class BookingService
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MailService $mailService,
        private readonly UserRepository $userRepository
    ) {
    }

    public function createBooking(
        string $title,
        Room $room,
        User $user,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
        int $participantsCount,
        bool $isPrivate = false,
        array $participantIds = []
    ): Booking {
        $now = new DateTimeImmutable();
        if ($startedAt < $now) {
            throw new InvalidArgumentException('Cannot create booking in the past');
        }

        if ($endedAt <= $startedAt) {
            throw new InvalidArgumentException('End time must be after start time');
        }

        $booking = new Booking();
        $booking->setTitle($title);
        $booking->setRoom($room);
        $booking->setUser($user);
        $booking->setStartedAt($startedAt);
        $booking->setEndedAt($endedAt);
        $booking->setParticipantsCount($participantsCount);
        $booking->setIsPrivate($isPrivate);

        if (!empty($participantIds)) {
            $this->addParticipants($booking, $participantIds);
        }

        $this->bookingRepository->save($booking, true);

        // Send confirmation email to organizer
        $this->mailService->sendBookingConfirmation($user, $booking, $room, $booking->getParticipants()->toArray());

        // Send invitation emails to participants
        foreach ($booking->getParticipants() as $participant) {
            $this->mailService->sendParticipantInvitation($participant, $booking, $room, $user);
        }

        return $booking;
    }

    public function updateBooking(
        Booking $booking,
        ?string $title = null,
        ?Room $room = null,
        ?DateTimeImmutable $startedAt = null,
        ?DateTimeImmutable $endedAt = null,
        ?int $participantsCount = null,
        ?bool $isPrivate = null,
        ?array $participantIds = null
    ): Booking {
        if ($title !== null) {
            $booking->setTitle($title);
        }

        if ($room !== null) {
            $booking->setRoom($room);
        }

        if ($startedAt !== null) {
            $booking->setStartedAt($startedAt);
        }

        if ($endedAt !== null) {
            $booking->setEndedAt($endedAt);
        }

        if ($participantsCount !== null) {
            $booking->setParticipantsCount($participantsCount);
        }

        if ($isPrivate !== null) {
            $booking->setIsPrivate($isPrivate);
        }

        if ($participantIds !== null) {
            foreach ($booking->getParticipants() as $participant) {
                $booking->removeParticipant($participant);
            }
            $this->addParticipants($booking, $participantIds);
        }

        $this->bookingRepository->flush();

        return $booking;
    }

    public function cancelBooking(Booking $booking): void
    {
        $booking->setStatus('cancelled');
        $this->bookingRepository->flush();
    }

    public function findConflictingBooking(
        Room $room,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
        ?Uuid $excludeBookingId = null
    ): ?Booking {
        return $this->bookingRepository->findConflictingBooking(
            $room,
            $startedAt,
            $endedAt,
            $excludeBookingId
        );
    }

    public function canUserCancelBooking(Booking $booking, User $user): bool
    {
        return $booking->getUser()->getId() === $user->getId()
            || in_array('ROLE_ADMIN', $user->getRoles());
    }

    public function canUserEditBooking(Booking $booking, User $user): bool
    {
        return $booking->getUser()->getId() === $user->getId()
            || in_array('ROLE_ADMIN', $user->getRoles());
    }

    public function handleCreateBooking(CreateBookingDTO $dto, User $user): Booking
    {
        $room = $this->findRoomByUuid($dto->roomId);

        $startedAt = $this->parseDateTime($dto->startedAt);
        $endedAt = $this->parseDateTime($dto->endedAt);

        $this->checkForConflicts($room, $startedAt, $endedAt);

        return $this->createBooking(
            $dto->title,
            $room,
            $user,
            $startedAt,
            $endedAt,
            $dto->participantsCount,
            $dto->isPrivate,
            $dto->participantIds
        );
    }

    public function handleUpdateBooking(Booking $booking, UpdateBookingDTO $dto): Booking
    {
        $room = $dto->roomId !== null ? $this->findRoomByUuid($dto->roomId) : null;

        $startedAt = $dto->startedAt !== null ? $this->parseDateTime($dto->startedAt) : null;
        $endedAt = $dto->endedAt !== null ? $this->parseDateTime($dto->endedAt) : null;

        $finalStartedAt = $startedAt ?? $booking->getStartedAt();
        $finalEndedAt = $endedAt ?? $booking->getEndedAt();

        // Sprawdź czy data nie jest w przeszłości
        $now = new DateTimeImmutable();
        if ($finalStartedAt < $now) {
            throw new InvalidArgumentException('Cannot update booking to past date');
        }

        if ($finalStartedAt >= $finalEndedAt) {
            throw new InvalidArgumentException('End time must be after start time');
        }

        $finalRoom = $room ?? $booking->getRoom();
        if ($room !== null || $startedAt !== null || $endedAt !== null) {
            $this->checkForConflicts($finalRoom, $finalStartedAt, $finalEndedAt, $booking->getId());
        }

        return $this->updateBooking(
            $booking,
            $dto->title,
            $room,
            $startedAt,
            $endedAt,
            $dto->participantsCount,
            $dto->isPrivate,
            $dto->participantIds
        );
    }

    public function handleCancelBooking(Booking $booking): CancelBookingResponseDTO
    {
        if ($booking->getStatus() === 'cancelled') {
            throw new InvalidArgumentException('Booking already cancelled');
        }

        $this->cancelBooking($booking);

        return new CancelBookingResponseDTO(
            'Booking cancelled successfully',
            new BookingResponseDTO($booking)
        );
    }

    public function getBookingCounts(User $user): BookingCountsResponseDTO
    {
        $counts = $this->bookingRepository->getBookingCountsByUser($user);

        return new BookingCountsResponseDTO(
            $counts['count'],
            $counts['active'],
            $counts['completed'],
            $counts['cancelled']
        );
    }

    public function getBookingCountsByOrganization(Organization $organization): BookingCountsResponseDTO
    {
        $counts = $this->bookingRepository->getBookingCountsByOrganization($organization);

        return new BookingCountsResponseDTO(
            $counts['count'],
            $counts['active'],
            $counts['completed'],
            $counts['cancelled']
        );
    }

    public function getTotalBookingStats(Organization $organization): BookingTotalStatsResponseDTO
    {
        $stats = $this->bookingRepository->getTotalBookingStats($organization);

        return new BookingTotalStatsResponseDTO(
            $stats['total'],
            $stats['thisMonth'],
            $stats['thisWeek'],
            $stats['today']
        );
    }

    /**
     * @return OccupancyRateByDayDTO[]
     */
    public function getOccupancyRateByDayOfWeek(Organization $organization): array
    {
        $occupancyData = $this->bookingRepository->getOccupancyRateByDayOfWeek($organization);
        
        $result = [];
        foreach ($occupancyData as $dayName => $rate) {
            $result[] = new OccupancyRateByDayDTO($dayName, $rate);
        }
        
        return $result;
    }

    public function getBookingsList(): array
    {
        $bookings = $this->bookingRepository->findByCriteria([], ['startedAt' => 'ASC']);

        return array_map(
            fn(Booking $booking) => (new BookingResponseDTO($booking))->toArray(),
            $bookings
        );
    }

    public function getBookingById(string $id): Booking
    {
        $uuid = $this->parseUuid($id);

        $booking = $this->bookingRepository->findById($uuid);
        if (!$booking) {
            throw new InvalidArgumentException('Booking not found');
        }

        return $booking;
    }

    private function findRoomByUuid(string $roomId): Room
    {
        $uuid = $this->parseUuid($roomId);

        $room = $this->entityManager->getRepository(Room::class)->find($uuid);
        if (!$room) {
            throw new InvalidArgumentException('Room not found');
        }

        return $room;
    }

    private function parseUuid(string $uuidString): Uuid
    {
        try {
            return Uuid::fromString($uuidString);
        } catch (Exception $e) {
            throw new InvalidArgumentException('Invalid UUID format');
        }
    }

    private function parseDateTime(string $dateTimeString): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($dateTimeString);
        } catch (Exception $e) {
            throw new InvalidArgumentException('Invalid date format');
        }
    }

    private function checkForConflicts(
        Room $room,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
        ?Uuid $excludeBookingId = null
    ): void {
        $conflictingBooking = $this->findConflictingBooking($room, $startedAt, $endedAt, $excludeBookingId);

        if ($conflictingBooking) {
            throw new InvalidArgumentException(
                json_encode([
                    'code' => 409,
                    'message' => 'Time slot already booked'
                ])
            );
        }
    }

    private function addParticipants(Booking $booking, array $participantIds): void
    {
        foreach ($participantIds as $participantId) {
            try {
                $participantUuid = Uuid::fromString($participantId);
                $participant = $this->entityManager->getRepository(User::class)->find($participantUuid);
                if ($participant) {
                    $booking->addParticipant($participant);
                }
            } catch (Exception $e) {
                continue;
            }
        }
    }

    public function getBookingTrend(Organization $organization): BookingTrendResponseDTO
    {
        $bookings = $this->bookingRepository->findByOrganization($organization);
        $now = new DateTimeImmutable();

        $dayNames = ['Nie', 'Pon', 'Wt', 'Śr', 'Czw', 'Pt', 'Sob'];
        
        $confirmed = array_fill_keys($dayNames, 0);
        $pending = array_fill_keys($dayNames, 0);
        $cancelled = array_fill_keys($dayNames, 0);

        foreach ($bookings as $booking) {
            $dayIndex = (int)$booking->getStartedAt()->format('w');
            $dayName = $dayNames[$dayIndex];
            $status = $booking->getStatus();

            if ($status === 'cancelled') {
                $cancelled[$dayName]++;
            } elseif ($status === 'completed') {
                $confirmed[$dayName]++;
            } elseif ($status === 'active') {
                if ($booking->getEndedAt() < $now) {
                    $confirmed[$dayName]++;
                } else {
                    $pending[$dayName]++;
                }
            }
        }

        return new BookingTrendResponseDTO(
            confirmed: $confirmed,
            pending: $pending,
            cancelled: $cancelled
        );
    }

    public function createRecurringBooking(
        Room $room,
        User $user,
        string $type,
        string $startTime,
        string $endTime,
        array $daysOfWeek,
        int $weeksAhead = 12
    ): RecurringBookingResponseDTO {
        $title = $type === 'cleaning' ? 'Sprzątanie' : 'Konserwacja';
        $createdBookings = [];
        $now = new DateTimeImmutable();
        
        // Calculate end date (weeksAhead from now)
        $endDate = $now->modify("+{$weeksAhead} weeks");

        // Start from tomorrow to avoid creating bookings in the past
        $currentDate = $now->modify('+1 day')->setTime(0, 0);

        while ($currentDate <= $endDate) {
            $dayOfWeek = (int)$currentDate->format('N'); // 1=Monday, 7=Sunday
            
            if (in_array($dayOfWeek, $daysOfWeek)) {
                // Parse time
                [$startHour, $startMinute] = explode(':', $startTime);
                [$endHour, $endMinute] = explode(':', $endTime);
                
                $startedAt = $currentDate->setTime((int)$startHour, (int)$startMinute);
                $endedAt = $currentDate->setTime((int)$endHour, (int)$endMinute);

                // Check for conflicts
                $hasConflict = $this->findConflictingBooking($room, $startedAt, $endedAt);
                
                if (!$hasConflict) {
                    $booking = new Booking();
                    $booking->setTitle($title);
                    $booking->setRoom($room);
                    $booking->setUser($user);
                    $booking->setStartedAt($startedAt);
                    $booking->setEndedAt($endedAt);
                    $booking->setParticipantsCount(0);
                    $booking->setIsPrivate(true);
                    $booking->setStatus('active');

                    $this->entityManager->persist($booking);
                    $createdBookings[] = $booking->getId()->toRfc4122();
                }
            }

            $currentDate = $currentDate->modify('+1 day');
        }

        $this->entityManager->flush();

        return new RecurringBookingResponseDTO(
            createdCount: count($createdBookings),
            bookingIds: $createdBookings
        );
    }
}
