<?php

declare(strict_types=1);

namespace App\Feature\User\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class GenericSuccessResponseDTO
{
    public function __construct(
        public string $message
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
