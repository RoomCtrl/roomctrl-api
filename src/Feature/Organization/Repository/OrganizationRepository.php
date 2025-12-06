<?php

declare(strict_types=1);

namespace App\Feature\Organization\Repository;

use App\Feature\Organization\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Organization>
 */
class OrganizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Organization::class);
    }

    public function save(Organization $organization, bool $flush = false): void
    {
        $this->getEntityManager()->persist($organization);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Organization $organization, bool $flush = false): void
    {
        $this->getEntityManager()->remove($organization);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findById(Uuid $id): ?Organization
    {
        return $this->find($id);
    }

    public function findAll(): array
    {
        return parent::findAll();
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
