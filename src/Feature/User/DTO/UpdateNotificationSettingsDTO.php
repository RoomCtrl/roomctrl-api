<?php

declare(strict_types=1);

namespace App\Feature\User\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateNotificationSettingsDTO
{
    #[Assert\NotNull(message: 'Email notifications enabled field is required')]
    #[Assert\Type(type: 'bool', message: 'Email notifications enabled must be a boolean')]
    public bool $emailNotificationsEnabled;
}
