<?php

declare(strict_types=1);

namespace App\Feature\Organization\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateOrganizationDTO
{
    #[Assert\NotBlank(message: 'REGON is required')]
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

    #[Assert\NotBlank(message: 'Organization name is required')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Organization name must be at least {{ limit }} characters long.',
        maxMessage: 'Organization name cannot be longer than {{ limit }} characters.'
    )]
    public string $name;

    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'The email "{{ value }}" is not a valid email.')]
    public string $email;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->regon = $data['regon'] ?? '';
        $dto->name = $data['name'] ?? '';
        $dto->email = $data['email'] ?? '';

        return $dto;
    }
}
