<?php

declare(strict_types=1);

namespace App\Feature\Auth\Service;

use App\Feature\Auth\DTO\RegisterRequestDTO;
use App\Feature\Auth\DTO\UserInfoDTO;
use App\Feature\Auth\Repository\AuthRepository;
use App\Feature\Mail\Service\MailServiceInterface;
use App\Feature\Organization\Entity\Organization;
use App\Feature\User\Entity\User;
use InvalidArgumentException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AuthService implements AuthServiceInterface
{
    private AuthRepository $authRepository;
    private UserPasswordHasherInterface $passwordHasher;
    private MailServiceInterface $mailService;

    public function __construct(
        AuthRepository $authRepository,
        UserPasswordHasherInterface $passwordHasher,
        MailServiceInterface $mailService
    ) {
        $this->authRepository = $authRepository;
        $this->passwordHasher = $passwordHasher;
        $this->mailService = $mailService;
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

        $this->mailService->sendWelcomeEmail($user, $organization);

        return $user;
    }

    private function validateRegistrationData(RegisterRequestDTO $dto): void
    {
        $conflicts = $this->authRepository->checkRegistrationConflicts(
            $dto->username,
            $dto->email,
            $dto->phone,
            $dto->regon,
            $dto->organizationEmail
        );

        if ($conflicts['userExists']) {
            throw new InvalidArgumentException('This username is already taken.');
        }

        if ($conflicts['userEmailExists']) {
            throw new InvalidArgumentException('This email is already registered.');
        }

        if ($conflicts['userPhoneExists']) {
            throw new InvalidArgumentException('This phone number is already in use.');
        }

        if ($conflicts['orgRegonExists']) {
            throw new InvalidArgumentException('This REGON is already registered.');
        }

        if ($conflicts['orgEmailExists']) {
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
        $user->setRoles(['ROLE_ADMIN']);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        return $user;
    }
}
