<?php

declare(strict_types=1);

namespace App\Feature\Auth\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class RegisterRequestDTO
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
    #[Assert\Length(
        min: 6,
        minMessage: 'Password must be at least {{ limit }} characters long.'
    )]
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
    #[Assert\Length(
        max: 100,
        maxMessage: 'Email cannot be longer than {{ limit }} characters.'
    )]
    public string $email;

    #[Assert\NotBlank(message: 'Phone cannot be blank.')]
    #[Assert\Length(
        max: 20,
        maxMessage: 'Phone cannot be longer than {{ limit }} characters.'
    )]
    public string $phone;

    #[Assert\NotBlank(message: 'Organization REGON cannot be blank.')]
    #[Assert\Length(
        min: 9,
        max: 14,
        minMessage: 'REGON must be at least {{ limit }} characters long.',
        maxMessage: 'REGON cannot be longer than {{ limit }} characters.'
    )]
    #[Assert\Regex(
        pattern: '/^\d+$/',
        message: 'REGON must contain only digits.'
    )]
    public string $regon;

    #[Assert\NotBlank(message: 'Organization name cannot be blank.')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Organization name must be at least {{ limit }} characters long.',
        maxMessage: 'Organization name cannot be longer than {{ limit }} characters.'
    )]
    public string $organizationName;

    #[Assert\NotBlank(message: 'Organization email cannot be blank.')]
    #[Assert\Email(message: 'The organization email "{{ value }}" is not a valid email.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Organization email cannot be longer than {{ limit }} characters.'
    )]
    public string $organizationEmail;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->username = $data['username'] ?? '';
        $dto->password = $data['password'] ?? '';
        $dto->firstName = $data['firstName'] ?? '';
        $dto->lastName = $data['lastName'] ?? '';
        $dto->email = $data['email'] ?? '';
        $dto->phone = $data['phone'] ?? '';
        $dto->regon = $data['regon'] ?? '';
        $dto->organizationName = $data['organizationName'] ?? '';
        $dto->organizationEmail = $data['organizationEmail'] ?? '';
        
        return $dto;
    }
}
