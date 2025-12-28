<?php

declare(strict_types=1);

namespace App\Feature\User\DTO;

use App\Feature\User\Entity\User;

class UserResponseDTO
{
    public string $id;
    public string $username;
    public string $firstName;
    public string $lastName;
    public string $email;
    public string $phone;
    public array $roles;
    public bool $isActive;
    public ?array $organization = null;

    public static function fromEntity(User $user, bool $withDetails = false): self
    {
        $dto = new self();
        $dto->id = $user->getId()->toRfc4122();
        $dto->username = $user->getUsername();
        $dto->firstName = $user->getFirstName();
        $dto->lastName = $user->getLastName();
        $dto->email = $user->getEmail();
        $dto->phone = $user->getPhone();
        $dto->roles = $user->getRoles();
        $dto->isActive = $user->getIsActive();

        if ($withDetails) {
            $organization = $user->getOrganization();
            if ($organization) {
                $dto->organization = [
                    'id' => $organization->getId()->toRfc4122(),
                    'regon' => $organization->getRegon(),
                    'name' => $organization->getName(),
                    'email' => $organization->getEmail()
                ];
            }
        }

        return $dto;
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'username' => $this->username,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
            'roles' => $this->roles,
            'isActive' => $this->isActive,
        ];

        if ($this->organization !== null) {
            $data['organization'] = $this->organization;
        }

        return $data;
    }
}

