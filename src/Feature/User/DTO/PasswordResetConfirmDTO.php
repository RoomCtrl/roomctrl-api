<?php

declare(strict_types=1);

namespace App\Feature\User\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class PasswordResetConfirmDTO
{
    #[Assert\NotBlank(message: 'Token cannot be blank.')]
    public string $token;

    #[Assert\NotBlank(message: 'New password cannot be blank.')]
    public string $newPassword;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->token = $data['token'] ?? '';
        $dto->newPassword = $data['newPassword'] ?? '';

        return $dto;
    }
}
