<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateBookingRequest
{
    #[Assert\Length(min: 3, max: 255)]
    public ?string $title = null;

    #[Assert\Uuid]
    public ?string $roomId = null;

    #[Assert\DateTime]
    public ?string $startedAt = null;

    #[Assert\DateTime]
    public ?string $endedAt = null;

    #[Assert\Positive]
    public ?int $participantsCount = null;

    public ?bool $isPrivate = null;

    #[Assert\Type('array')]
    public ?array $participantIds = null;

    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->title = $data['title'] ?? null;
        $request->roomId = $data['roomId'] ?? null;
        $request->startedAt = $data['startedAt'] ?? null;
        $request->endedAt = $data['endedAt'] ?? null;
        $request->participantsCount = $data['participantsCount'] ?? null;
        $request->isPrivate = $data['isPrivate'] ?? null;
        $request->participantIds = $data['participantIds'] ?? null;

        return $request;
    }
}
