<?php

declare(strict_types=1);

namespace App\Feature\Room\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateRoomRequest
{
    #[Assert\NotBlank(message: 'Room name is required')]
    #[Assert\Length(min: 1, max: 100)]
    public string $roomName;

    #[Assert\NotNull]
    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 200)]
    public int $capacity;

    #[Assert\NotNull]
    #[Assert\Positive]
    public float $size;

    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    public string $location;

    #[Assert\NotBlank]
    #[Assert\Length(max: 50)]
    public string $access;

    public ?string $description = null;

    #[Assert\Length(max: 100)]
    public ?string $lighting = null;

    #[Assert\Type('array')]
    public ?array $airConditioning = null;

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $organizationId;

    #[Assert\Type('array')]
    #[Assert\All([
        new Assert\Collection([
            'name' => [new Assert\NotBlank(), new Assert\Length(max: 100)],
            'category' => [new Assert\NotBlank(), new Assert\Choice(choices: ['video', 'audio', 'computer', 'accessory', 'furniture'])],
            'quantity' => [new Assert\NotNull(), new Assert\Positive()]
        ])
    ])]
    public array $equipment = [];

    #[Assert\Choice(choices: ['available', 'occupied', 'maintenance'])]
    public ?string $status = 'available';

    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->roomName = $data['roomName'] ?? '';
        $request->capacity = $data['capacity'] ?? 0;
        $request->size = (float) ($data['size'] ?? 0);
        $request->location = $data['location'] ?? '';
        $request->access = $data['access'] ?? '';
        $request->description = $data['description'] ?? null;
        $request->lighting = $data['lighting'] ?? null;
        $request->airConditioning = $data['airConditioning'] ?? null;
        $request->organizationId = $data['organizationId'] ?? '';
        $request->equipment = $data['equipment'] ?? [];
        $request->status = $data['status'] ?? 'available';

        return $request;
    }
}
