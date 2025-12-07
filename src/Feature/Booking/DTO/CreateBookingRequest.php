<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateBookingRequest
{
    #[Assert\NotBlank(message: 'Title is required')]
    #[Assert\Length(min: 3, max: 255)]
    public string $title;

    #[Assert\NotBlank(message: 'Room ID is required')]
    #[Assert\Uuid]
    public string $roomId;

    #[Assert\NotBlank(message: 'Start time is required')]
    public string $startedAt;

    #[Assert\NotBlank(message: 'End time is required')]
    public string $endedAt;

    #[Assert\NotNull]
    #[Assert\Positive]
    public int $participantsCount;

    public bool $isPrivate = false;

    #[Assert\Type('array')]
    public array $participantIds = [];

    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->title = $data['title'] ?? '';
        $request->roomId = $data['roomId'] ?? '';
        $request->startedAt = $data['startedAt'] ?? '';
        $request->endedAt = $data['endedAt'] ?? '';
        $request->participantsCount = $data['participantsCount'] ?? 0;
        $request->isPrivate = $data['isPrivate'] ?? false;
        $request->participantIds = $data['participantIds'] ?? [];

        return $request;
    }
}
