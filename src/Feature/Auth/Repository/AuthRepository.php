<?php

declare(strict_types=1);

namespace App\Feature\Auth\Repository;

use App\Feature\User\Entity\User;
use App\Feature\Organization\Entity\Organization;
use Doctrine\ORM\EntityManagerInterface;

class AuthRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function findUserByUsername(string $username): ?User
    {
        return $this->entityManager->getRepository(User::class)->findOneBy([
            'username' => $username
        ]);
    }

    public function findOrganizationByRegon(string $regon): ?Organization
    {
        return $this->entityManager->getRepository(Organization::class)->findOneBy([
            'regon' => $regon
        ]);
    }

    public function findOrganizationByEmail(string $email): ?Organization
    {
        return $this->entityManager->getRepository(Organization::class)->findOneBy([
            'email' => $email
        ]);
    }

    public function save(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function saveAndFlush(object $entity): void
    {
        $this->save($entity);
        $this->flush();
    }

    public function saveMultipleAndFlush(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->save($entity);
        }
        $this->flush();
    }
}

