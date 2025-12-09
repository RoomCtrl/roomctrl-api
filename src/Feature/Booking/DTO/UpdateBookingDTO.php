<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateBookingDTO
{
    #[Assert\Length(min: 3, max: 255)]
    public ?string $title = null;

    #[Assert\Uuid]
    public ?string $roomId = null;

    #[Assert\Regex(
        pattern: '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})?$/',
        message: 'Invalid datetime format. Use ISO 8601 format (e.g., 2025-12-08T13:16:23 or 2025-12-08T13:16:23Z)'
    )]
    public ?string $startedAt = null;

    #[Assert\Regex(
        pattern: '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(Z|[+-]\d{2}:\d{2})?$/',
        message: 'Invalid datetime format. Use ISO 8601 format (e.g., 2025-12-08T13:16:23 or 2025-12-08T13:16:23Z)'
    )]
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
