<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Booking\DTO\RoomDTO;
use App\Feature\Booking\DTO\UserDTO;
use App\Feature\Booking\DTO\ParticipantDTO;

class BookingResponseDTO
{
    public string $id;
    public string $title;
    public string $startedAt;
    public string $endedAt;
    public int $participantsCount;
    /** @var ParticipantDTO[] */
    public array $participants;
    public bool $isPrivate;
    public string $status;
    public RoomDTO $room;
    public UserDTO $user;
    public string $createdAt;

    public function __construct(Booking $booking)
    {
        $this->id = $booking->getId()->toRfc4122();
        $this->title = $booking->getTitle();
        $this->startedAt = $booking->getStartedAt()->format('c');
        $this->endedAt = $booking->getEndedAt()->format('c');
        $this->participantsCount = $booking->getParticipantsCount();
        $this->isPrivate = $booking->isPrivate();
        $this->status = $booking->getStatus();
        $this->createdAt = $booking->getCreatedAt()->format('c');

        $room = $booking->getRoom();
        $this->room = new RoomDTO(
            $room->getId()->toRfc4122(),
            $room->getRoomName(),
            $room->getLocation()
        );

        $user = $booking->getUser();
        $this->user = new UserDTO(
            $user->getId()->toRfc4122(),
            $user->getUsername(),
            $user->getFirstName(),
            $user->getLastName()
        );

        $this->participants = [];
        foreach ($booking->getParticipants() as $participant) {
            $this->participants[] = new ParticipantDTO(
                $participant->getId()->toRfc4122(),
                $participant->getUsername(),
                $participant->getFirstName(),
                $participant->getLastName(),
                $participant->getEmail()
            );
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'startedAt' => $this->startedAt,
            'endedAt' => $this->endedAt,
            'participantsCount' => $this->participantsCount,
            'participants' => array_map(fn($p) => $p->toArray(), $this->participants),
            'isPrivate' => $this->isPrivate,
            'status' => $this->status,
            'room' => $this->room->toArray(),
            'user' => $this->user->toArray(),
            'createdAt' => $this->createdAt
        ];
    }
}
