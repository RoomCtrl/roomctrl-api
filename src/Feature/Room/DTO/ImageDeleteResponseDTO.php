<?php

declare(strict_types=1);

namespace App\Feature\Room\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class ImageDeleteResponseDTO
{
    public function __construct(
        private string $message,
        private ?int $deletedCount = null,
        private ?string $deletedPath = null,
        private int $code = Response::HTTP_OK
    ) {
    }

    public function toArray(): array
    {
        $data = [
            'code' => $this->code,
            'message' => $this->message
        ];

        if ($this->deletedCount !== null) {
            $data['deletedCount'] = $this->deletedCount;
        }

        if ($this->deletedPath !== null) {
            $data['deletedPath'] = $this->deletedPath;
        }

        return $data;
    }
}
