<?php

declare(strict_types=1);

namespace App\Feature\Organization\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class OrganizationDeleteResultDTO
{
    public function __construct(
        private bool $success,
        private int $code,
        private ?string $message = null
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getCode(): int
    {
        return $this->code;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function toArray(): array
    {
        $result = [
            'code' => $this->code
        ];

        if ($this->message !== null) {
            $result['message'] = $this->message;
        }

        return $result;
    }

    public static function success(): self
    {
        return new self(true, Response::HTTP_NO_CONTENT);
    }

    public static function conflict(string $message): self
    {
        return new self(false, Response::HTTP_CONFLICT, $message);
    }

    public static function error(string $message): self
    {
        return new self(false, Response::HTTP_INTERNAL_SERVER_ERROR, $message);
    }
}
