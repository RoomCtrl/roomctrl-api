<?php

declare(strict_types=1);

namespace App\Feature\Auth\Service;

use App\Feature\Auth\DTO\RegisterRequestDTO;
use App\Feature\User\Entity\User;

interface AuthServiceInterface
{
    public function getCurrentUserInfo(User $user, bool $withOrganization = false): array;

    public function register(RegisterRequestDTO $dto): User;
}
