<?php

declare(strict_types=1);

namespace App\Feature\Booking\Service;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\Repository\BookingRepository;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
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
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $endedAt,
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

    public function cancelBooking(Booking $booking): void
    {
        $booking->setStatus('cancelled');
        $this->bookingRepository->flush();
    }

    public function findConflictingBooking(
        Room $room,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $endedAt,
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

    private function addParticipants(Booking $booking, array $participantIds): void
    {
        foreach ($participantIds as $participantId) {
            try {
                $participantUuid = Uuid::fromString($participantId);
                $participant = $this->entityManager->getRepository(User::class)->find($participantUuid);
                if ($participant) {
                    $booking->addParticipant($participant);
                }
            } catch (\Exception $e) {
                continue;
            }
        }
    }
}
