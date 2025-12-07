<?php

declare(strict_types=1);

namespace App\Feature\Booking\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
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
            // Clear existing participants and add new ones
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
