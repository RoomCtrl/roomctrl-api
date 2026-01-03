<?php

declare(strict_types=1);

namespace App\Feature\User\DTO;

use Symfony\Component\HttpFoundation\Response;

readonly class NotificationSettingsResponseDTO
{
    public function __construct(
        public bool $emailNotificationsEnabled,
        public string $message = 'Notification settings updated successfully'
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => Response::HTTP_OK,
            'message' => $this->message,
            'emailNotificationsEnabled' => $this->emailNotificationsEnabled
        ];
    }
}
