<?php

declare(strict_types=1);

namespace App\Feature\Download\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class FileNotFoundResponseDTO
{
    public function __construct(
        public string $message
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => Response::HTTP_NOT_FOUND,
            'message' => $this->message
        ];
    }
}
