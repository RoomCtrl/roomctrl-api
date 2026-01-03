<?php

declare(strict_types=1);

namespace App\Feature\Organization\DTO;

use App\Feature\Organization\Entity\Organization;

class OrganizationResponseDTO
{
    public string $id;
    public string $regon;
    public string $name;
    public string $email;
    public ?int $usersCount = null;

    private function __construct(
        string $id,
        string $regon,
        string $name,
        string $email,
        ?int $usersCount = null
    ) {
        $this->id = $id;
        $this->regon = $regon;
        $this->name = $name;
        $this->email = $email;
        $this->usersCount = $usersCount;
    }

    public static function fromEntity(Organization $organization, bool $withUsers = false): self
    {
        return new self(
            $organization->getId()->toRfc4122(),
            $organization->getRegon(),
            $organization->getName(),
            $organization->getEmail(),
            $withUsers ? $organization->getUsers()->count() : null
        );
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'regon' => $this->regon,
            'name' => $this->name,
            'email' => $this->email
        ];

        if ($this->usersCount !== null) {
            $data['usersCount'] = $this->usersCount;
        }

        return $data;
    }
}
