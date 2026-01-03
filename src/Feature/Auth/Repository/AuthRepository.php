<?php

declare(strict_types=1);

namespace App\Feature\Auth\Repository;

use App\Feature\User\Entity\User;
use App\Feature\Organization\Entity\Organization;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;

class AuthRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function save(object $entity): void
    {
        $this->entityManager->persist($entity);
    }

    public function flush(): void
    {
        $this->entityManager->flush();
    }

    public function saveMultipleAndFlush(array $entities): void
    {
        foreach ($entities as $entity) {
            $this->save($entity);
        }
        $this->flush();
    }

    public function checkRegistrationConflicts(
        string $username,
        string $email,
        string $phone,
        string $regon,
        string $organizationEmail
    ): array {
        $qb = $this->entityManager->createQueryBuilder();
        $userResult = $qb
            ->select('u.username as username, u.email as email, u.phone as phone')
            ->from(User::class, 'u')
            ->where('u.username = :username OR u.email = :email OR u.phone = :phone')
            ->setParameter('username', $username)
            ->setParameter('email', $email)
            ->setParameter('phone', $phone)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        $qb = $this->entityManager->createQueryBuilder();
        $orgResult = $qb
            ->select('o.regon as regon, o.email as email')
            ->from(Organization::class, 'o')
            ->where('o.regon = :regon OR o.email = :email')
            ->setParameter('regon', $regon)
            ->setParameter('email', $organizationEmail)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        return [
            'userExists' => $userResult && $userResult['username'] === $username,
            'userEmailExists' => $userResult && $userResult['email'] === $email,
            'userPhoneExists' => $userResult && $userResult['phone'] === $phone,
            'orgRegonExists' => $orgResult && $orgResult['regon'] === $regon,
            'orgEmailExists' => $orgResult && $orgResult['email'] === $organizationEmail,
        ];
    }
}
