<?php

declare(strict_types=1);

namespace App\Feature\Issue\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateNoteDTO
{
    #[Assert\NotBlank(message: 'Content cannot be blank.')]
    #[Assert\Length(min: 5, max: 2000, minMessage: 'Content must be at least {{ limit }} characters.', maxMessage: 'Content cannot exceed {{ limit }} characters.')]
    public string $content;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->content = $data['content'] ?? '';

        return $dto;
    }
}
