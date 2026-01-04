<?php

declare(strict_types=1);

namespace App\Feature\User\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class UserCreatedResponseDTO
{
    public function __construct(
        public string $id,
        public string $message = 'User created successfully'
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
