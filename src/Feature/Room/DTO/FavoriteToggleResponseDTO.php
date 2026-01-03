<?php

declare(strict_types=1);

namespace App\Feature\Room\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class FavoriteToggleResponseDTO
{
    public function __construct(
        private string $message,
        private bool $isFavorite,
        private int $code = Response::HTTP_OK
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'message' => $this->message,
            'isFavorite' => $this->isFavorite
        ];
    }
}
