<?php

declare(strict_types=1);

namespace App\Feature\Organization\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class OrganizationCreatedResponseDTO
{
    public function __construct(
        private string $id,
        private string $message = 'Organization created successfully'
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => Response::HTTP_CREATED,
            'message' => $this->message,
            'id' => $this->id
        ];
    }
}
