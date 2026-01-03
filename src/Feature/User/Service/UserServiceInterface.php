<?php

declare(strict_types=1);

namespace App\Feature\User\Service;

use App\Feature\User\DTO\CreateUserDTO;
use App\Feature\User\DTO\UpdateUserDTO;
use App\Feature\User\DTO\UserResponseDTO;
use App\Feature\User\Entity\User;
use App\Feature\Organization\Entity\Organization;
use Symfony\Component\Uid\Uuid;

interface UserServiceInterface
{
    /**
     * Get all users, optionally filtered by organization
     *
     * @return array<int, array>
     */
    public function getAllUsers(bool $withDetails = false, ?Organization $organization = null): array;

    /**
     * Get user by UUID
     */
    public function getUserById(Uuid $uuid, bool $withDetails = false): ?UserResponseDTO;

    /**
     * Create a new user
     */
    public function createUser(CreateUserDTO $dto): User;

    /**
     * Update existing user
     */
    public function updateUser(User $user, UpdateUserDTO $dto): void;

    /**
     * Delete user and cancel their active bookings
     */
    public function deleteUser(User $user): void;

    /**
     * Validate user entity
     *
     * @return array<int, string>
     */
    public function validateUser(User $user): array;

    /**
     * Validate DTO object
     *
     * @return array<int, string>
     */
    public function validateDTO(object $dto): array;

    /**
     * Request password reset - generate token and send email
     */
    public function requestPasswordReset(string $email): bool;

    /**
     * Confirm password reset using token
     */
    public function confirmPasswordReset(string $token, string $newPassword): bool;

    /**
     * Check if current user can access target user (same organization)
     */
    public function canCurrentUserAccessUser(User $targetUser, User $currentUser): bool;
}
