<?php

declare(strict_types=1);

namespace App\Feature\User\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetRequestDTO
{
    #[Assert\NotBlank(message: 'Email cannot be blank.')]
    #[Assert\Email(message: 'The email "{{ value }}" is not a valid email.')]
    public string $email;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->email = $data['email'] ?? '';

        return $dto;
    }
}
