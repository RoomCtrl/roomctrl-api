<?php

declare(strict_types=1);

namespace App\Feature\Room\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateRoomRequest
{
    #[Assert\Length(min: 1, max: 100)]
    public ?string $roomName = null;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 200)]
    public ?int $capacity = null;

    #[Assert\Positive]
    public ?float $size = null;

    #[Assert\Length(max: 255)]
    public ?string $location = null;

    #[Assert\Length(max: 50)]
    public ?string $access = null;

    public ?string $description = null;

    #[Assert\Length(max: 100)]
    public ?string $lighting = null;

    #[Assert\Type('array')]
    public ?array $airConditioning = null;

    #[Assert\Choice(choices: ['available', 'out_of_use'])]
    public ?string $status = null;

    #[Assert\Type('array')]
    public ?array $equipment = null;

    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->roomName = $data['roomName'] ?? null;
        $request->capacity = isset($data['capacity']) ? (int) $data['capacity'] : null;
        $request->size = isset($data['size']) ? (float) $data['size'] : null;
        $request->location = $data['location'] ?? null;
        $request->access = $data['access'] ?? null;
        $request->description = $data['description'] ?? null;
        $request->lighting = $data['lighting'] ?? null;
        $request->airConditioning = $data['airConditioning'] ?? null;
        $request->status = $data['status'] ?? null;
        $request->equipment = $data['equipment'] ?? null;

        return $request;
    }
}
