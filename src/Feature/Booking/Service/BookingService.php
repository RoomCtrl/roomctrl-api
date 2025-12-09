<?php

declare(strict_types=1);

namespace App\Feature\Booking\Service;

use App\Feature\Booking\DTO\BookingCountsResponseDTO;
use App\Feature\Booking\DTO\BookingResponseDTO;
use App\Feature\Booking\DTO\CancelBookingResponseDTO;
use App\Feature\Booking\DTO\CreateBookingDTO;
use App\Feature\Booking\DTO\UpdateBookingDTO;
use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class BookingService
{
    public function __construct(
        private readonly BookingRepository $bookingRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator
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

    public function validateBooking(Booking $booking): array
    {
        $errors = $this->validator->validate($booking);
        $errorMessages = [];

        foreach ($errors as $error) {
            $errorMessages[] = $error->getMessage();
        }

        return $errorMessages;
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

    public function validateDTO(object $dto): void
    {
        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            throw new InvalidArgumentException((string) $errors);
        }
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
                    'error' => 'Time slot already booked',
                    'conflictingBooking' => (new BookingResponseDTO($conflictingBooking))->toArray()
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
}
