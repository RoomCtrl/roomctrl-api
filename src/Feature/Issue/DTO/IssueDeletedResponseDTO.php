<?php

declare(strict_types=1);

namespace App\Feature\Issue\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class IssueDeletedResponseDTO
{
    public function __construct(
        private string $message = 'Issue deleted successfully'
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
