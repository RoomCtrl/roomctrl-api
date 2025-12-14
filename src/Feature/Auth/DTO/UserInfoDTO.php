<?php

declare(strict_types=1);

namespace App\Feature\Auth\DTO;

use App\Feature\User\Entity\User;

class UserInfoDTO
{
    private string $id;
    private string $username;
    private array $roles;
    private string $firstName;
    private string $lastName;
    private string $email;
    private string $phone;
    private ?OrganizationDTO $organization = null;

    public function __construct(User $user, bool $withOrganization = false)
    {
        $this->id = $user->getId()->toRfc4122();
        $this->username = $user->getUsername();
        $this->roles = $user->getRoles();
        $this->firstName = $user->getFirstName();
        $this->lastName = $user->getLastName();
        $this->email = $user->getEmail();
        $this->phone = $user->getPhone();

        if ($withOrganization && $user->getOrganization()) {
            $this->organization = new OrganizationDTO($user->getOrganization());
        }
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'username' => $this->username,
            'roles' => $this->roles,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'email' => $this->email,
            'phone' => $this->phone,
        ];

        if ($this->organization !== null) {
            $data['organization'] = $this->organization->toArray();
        }

        return $data;
    }
}
