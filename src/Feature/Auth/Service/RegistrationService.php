<?php

declare(strict_types=1);

namespace App\Feature\Auth\Service;

use App\Feature\Auth\DTO\RegisterRequestDTO;
use App\Feature\User\Entity\User;
use App\Feature\Organization\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use InvalidArgumentException;

class RegistrationService
{
    private EntityManagerInterface $entityManager;
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ) {
        $this->entityManager = $entityManager;
        $this->passwordHasher = $passwordHasher;
    }

    public function register(RegisterRequestDTO $dto): User
    {
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy([
            'username' => $dto->username
        ]);

        if ($existingUser) {
            throw new InvalidArgumentException('This username is already taken.');
        }

        $existingOrganization = $this->entityManager->getRepository(Organization::class)->findOneBy([
            'regon' => $dto->regon
        ]);

        if ($existingOrganization) {
            throw new InvalidArgumentException('This REGON is already registered.');
        }

        $existingOrgEmail = $this->entityManager->getRepository(Organization::class)->findOneBy([
            'email' => $dto->organizationEmail
        ]);

        if ($existingOrgEmail) {
            throw new InvalidArgumentException('This organization email is already registered.');
        }

        $organization = new Organization();
        $organization->setRegon(strip_tags($dto->regon));
        $organization->setName(strip_tags($dto->organizationName));
        $organization->setEmail(strip_tags($dto->organizationEmail));

        $user = new User();
        $user->setUsername(strip_tags($dto->username));
        $user->setFirstName(strip_tags($dto->firstName));
        $user->setLastName(strip_tags($dto->lastName));
        $user->setEmail(strip_tags($dto->email));
        $user->setPhone(strip_tags($dto->phone));
        $user->setOrganization($organization);
        
        $hashedPassword = $this->passwordHasher->hashPassword($user, $dto->password);
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($organization);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
