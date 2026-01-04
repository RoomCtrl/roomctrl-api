<?php

declare(strict_types=1);

namespace App\Feature\Issue\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class IssueCreatedResponseDTO
{
    public function __construct(
        private string $id,
        private string $message = 'Issue created successfully'
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
