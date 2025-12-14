<?php

declare(strict_types=1);

namespace App\Feature\User\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateUserDTO
{
    #[Assert\Length(
        min: 3,
        max: 180,
        minMessage: 'Username must be at least {{ limit }} characters long.',
        maxMessage: 'Username cannot be longer than {{ limit }} characters.'
    )]
    public ?string $username = null;

    public ?string $password = null;

    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'First name must be at least {{ limit }} characters long.',
        maxMessage: 'First name cannot be longer than {{ limit }} characters.'
    )]
    public ?string $firstName = null;

    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Last name must be at least {{ limit }} characters long.',
        maxMessage: 'Last name cannot be longer than {{ limit }} characters.'
    )]
    public ?string $lastName = null;

    #[Assert\Email(message: 'The email "{{ value }}" is not a valid email.')]
    public ?string $email = null;

    public ?string $phone = null;

    public ?string $organizationId = null;

    public ?array $roles = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->username = $data['username'] ?? null;
        $dto->password = $data['password'] ?? null;
        $dto->firstName = $data['firstName'] ?? null;
        $dto->lastName = $data['lastName'] ?? null;
        $dto->email = $data['email'] ?? null;
        $dto->phone = $data['phone'] ?? null;
        $dto->organizationId = $data['organizationId'] ?? null;
        $dto->roles = $data['roles'] ?? null;

        return $dto;
    }
}

