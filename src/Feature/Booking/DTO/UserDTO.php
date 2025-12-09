<?php

declare(strict_types=1);

namespace App\Feature\Booking\DTO;

class UserDTO
{
    public string $id;
    public string $username;
    public string $firstName;
    public string $lastName;

    public function __construct(string $id, string $username, string $firstName, string $lastName)
    {
        $this->id = $id;
        $this->username = $username;
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName
        ];
    }
}
