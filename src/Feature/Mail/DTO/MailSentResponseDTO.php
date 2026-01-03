<?php

declare(strict_types=1);

namespace App\Feature\Mail\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class MailSentResponseDTO
{
    public function __construct(
        private string $message = 'Email has been sent successfully'
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
