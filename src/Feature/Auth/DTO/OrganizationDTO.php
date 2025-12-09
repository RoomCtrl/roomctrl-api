<?php

declare(strict_types=1);

namespace App\Feature\Auth\DTO;

use App\Feature\Organization\Entity\Organization;

class OrganizationDTO
{
    private string $id;
    private string $regon;
    private string $name;
    private string $email;

    public function __construct(Organization $organization)
    {
        $this->id = $organization->getId()->toRfc4122();
        $this->regon = $organization->getRegon();
        $this->name = $organization->getName();
        $this->email = $organization->getEmail();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'regon' => $this->regon,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
