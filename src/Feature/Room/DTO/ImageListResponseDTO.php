<?php

declare(strict_types=1);

namespace App\Feature\Room\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class ImageListResponseDTO
{
    public function __construct(
        private array $imagePaths,
        private int $code = Response::HTTP_OK
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'imagePaths' => $this->imagePaths
        ];
    }
}
