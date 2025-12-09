<?php

declare(strict_types=1);

namespace App\Feature\Auth\Service;

use App\Feature\Auth\DTO\UserInfoDTO;
use App\Feature\User\Entity\User;

class AuthService
{
    public function getCurrentUserInfo(User $user, bool $withOrganization = false): array
    {
        $userInfoDTO = new UserInfoDTO($user, $withOrganization);
        return $userInfoDTO->toArray();
    }
}
