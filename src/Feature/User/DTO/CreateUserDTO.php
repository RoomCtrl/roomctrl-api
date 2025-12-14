<?php

declare(strict_types=1);

namespace App\Feature\User\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateUserDTO
{
    #[Assert\NotBlank(message: 'Username cannot be blank.')]
    #[Assert\Length(
        min: 3,
        max: 180,
        minMessage: 'Username must be at least {{ limit }} characters long.',
        maxMessage: 'Username cannot be longer than {{ limit }} characters.'
    )]
    public string $username;

    #[Assert\NotBlank(message: 'Password cannot be blank.')]
    public string $password;

    #[Assert\NotBlank(message: 'First name cannot be blank.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'First name must be at least {{ limit }} characters long.',
        maxMessage: 'First name cannot be longer than {{ limit }} characters.'
    )]
    public string $firstName;

    #[Assert\NotBlank(message: 'Last name cannot be blank.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Last name must be at least {{ limit }} characters long.',
        maxMessage: 'Last name cannot be longer than {{ limit }} characters.'
    )]
    public string $lastName;

    #[Assert\NotBlank(message: 'Email cannot be blank.')]
    #[Assert\Email(message: 'The email "{{ value }}" is not a valid email.')]
    public string $email;

    #[Assert\NotBlank(message: 'Phone cannot be blank.')]
    public string $phone;

    #[Assert\NotBlank(message: 'Organization ID cannot be blank.')]
    public string $organizationId;

    public ?array $roles = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->username = $data['username'] ?? '';
        $dto->password = $data['password'] ?? '';
        $dto->firstName = $data['firstName'] ?? '';
        $dto->lastName = $data['lastName'] ?? '';
        $dto->email = $data['email'] ?? '';
        $dto->phone = $data['phone'] ?? '';
        $dto->organizationId = $data['organizationId'] ?? '';
        $dto->roles = $data['roles'] ?? null;

        return $dto;
    }
}

