<?php

declare(strict_types=1);

namespace App\Feature\Auth\Service;

use App\Feature\Auth\DTO\RegisterRequestDTO;
use App\Feature\Auth\DTO\UserInfoDTO;
use App\Feature\Auth\Repository\AuthRepository;
use App\Feature\Organization\Entity\Organization;
use App\Feature\User\Entity\User;
use InvalidArgumentException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService
{
    private AuthRepository $authRepository;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        AuthRepository $authRepository,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->authRepository = $authRepository;
        $this->passwordHasher = $passwordHasher;
    }

    public function getCurrentUserInfo(User $user, bool $withOrganization = false): array
    {
        $userInfoDTO = new UserInfoDTO($user, $withOrganization);
        return $userInfoDTO->toArray();
    }

    public function register(RegisterRequestDTO $dto): User
    {
        $this->validateRegistrationData($dto);

        $organization = $this->createOrganization($dto);
        $user = $this->createUser($dto, $organization);

        $this->authRepository->saveMultipleAndFlush([$organization, $user]);

        return $user;
    }

    private function validateRegistrationData(RegisterRequestDTO $dto): void
    {
        $existingUser = $this->authRepository->findUserByUsername($dto->username);
        if ($existingUser) {
            throw new InvalidArgumentException('This username is already taken.');
        }

        $existingUserEmail = $this->authRepository->findUserByEmail($dto->email);
        if ($existingUserEmail) {
            throw new InvalidArgumentException('This email is already registered.');
        }

        $existingUserPhone = $this->authRepository->findUserByPhone($dto->phone);
        if ($existingUserPhone) {
            throw new InvalidArgumentException('This phone number is already in use.');
        }

        $existingOrganization = $this->authRepository->findOrganizationByRegon($dto->regon);
        if ($existingOrganization) {
            throw new InvalidArgumentException('This REGON is already registered.');
        }

        $existingOrgEmail = $this->authRepository->findOrganizationByEmail($dto->organizationEmail);
        if ($existingOrgEmail) {
            throw new InvalidArgumentException('This organization email is already registered.');
        }
    }

    private function createOrganization(RegisterRequestDTO $dto): Organization
    {
        $organization = new Organization();
        $organization->setRegon(strip_tags($dto->regon));
        $organization->setName(strip_tags($dto->organizationName));
        $organization->setEmail(strip_tags($dto->organizationEmail));

        return $organization;
    }

    private function createUser(RegisterRequestDTO $dto, Organization $organization): User
    {
        $user = new User();
        $user->setUsername(strip_tags($dto->username));
        $user->setFirstName(strip_tags($dto->firstName));
        $user->setLastName(strip_tags($dto->lastName));
        $user->setEmail(strip_tags($dto->email));
        $user->setPhone(strip_tags($dto->phone));
        $user->setOrganization($organization);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        return $user;
    }
}
