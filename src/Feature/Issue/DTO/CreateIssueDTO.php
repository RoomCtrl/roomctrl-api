<?php

declare(strict_types=1);

namespace App\Feature\Issue\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateIssueDTO
{
    #[Assert\NotBlank(message: 'Room ID cannot be blank.')]
    public string $roomId;

    #[Assert\NotBlank(message: 'Category cannot be blank.')]
    #[Assert\Choice(choices: ['equipment', 'infrastructure', 'furniture'], message: 'Invalid category.')]
    public string $category;

    #[Assert\NotBlank(message: 'Description cannot be blank.')]
    #[Assert\Length(min: 10, max: 2000, minMessage: 'Description must be at least {{ limit }} characters.', maxMessage: 'Description cannot exceed {{ limit }} characters.')]
    public string $description;

    #[Assert\Choice(choices: ['low', 'medium', 'high', 'critical'], message: 'Invalid priority.')]
    public string $priority = 'medium';

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->roomId = $data['roomId'] ?? '';
        $dto->category = $data['category'] ?? '';
        $dto->description = $data['description'] ?? '';
        $dto->priority = $data['priority'] ?? 'medium';

        return $dto;
    }
}
