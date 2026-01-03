<?php

declare(strict_types=1);

namespace App\Feature\Organization\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class OrganizationUpdatedResponseDTO
{
    public function __construct(
        private string $message = 'Organization updated successfully'
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => Response::HTTP_OK,
            'message' => $this->message
        ];
    }
}
