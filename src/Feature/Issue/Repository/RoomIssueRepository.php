<?php

declare(strict_types=1);

namespace App\Feature\Issue\Repository;

use App\Feature\Issue\Entity\RoomIssue;
use App\Feature\Organization\Entity\Organization;
use App\Feature\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<RoomIssue>
 */
class RoomIssueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoomIssue::class);
    }

    public function findByUuid(Uuid $uuid): ?RoomIssue
    {
        return $this->findOneBy(['id' => $uuid]);
    }

    public function findByOrganization(Organization $organization, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.organization = :organization')
            ->setParameter('organization', $organization)
            ->orderBy('i.reportedAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function findByReporter(string $reporterId, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->join('i.reporter', 'r')
            ->where('r.id = :reporterId')
            ->setParameter('reporterId', $reporterId)
            ->orderBy('i.reportedAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('i.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    public function getIssueCountsByUser(User $user): array
    {
        $qb = $this->createQueryBuilder('i');
        
        $totalCount = (int) $qb
            ->select('COUNT(i.id)')
            ->where('i.reporter = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $pendingCount = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.reporter = :user')
            ->andWhere('i.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        $inProgressCount = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.reporter = :user')
            ->andWhere('i.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'in_progress')
            ->getQuery()
            ->getSingleScalarResult();

        $closedCount = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.reporter = :user')
            ->andWhere('i.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'closed')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'count' => $totalCount,
            'pending' => $pendingCount,
            'in_progress' => $inProgressCount,
            'closed' => $closedCount
        ];
    }

    public function getIssueCountsByOrganization(Organization $organization): array
    {
        $qb = $this->createQueryBuilder('i');
        
        $totalCount = (int) $qb
            ->select('COUNT(i.id)')
            ->where('i.organization = :organization')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getSingleScalarResult();

        $pendingCount = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.organization = :organization')
            ->andWhere('i.status = :status')
            ->setParameter('organization', $organization)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        $inProgressCount = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.organization = :organization')
            ->andWhere('i.status = :status')
            ->setParameter('organization', $organization)
            ->setParameter('status', 'in_progress')
            ->getQuery()
            ->getSingleScalarResult();

        $closedCount = (int) $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.organization = :organization')
            ->andWhere('i.status = :status')
            ->setParameter('organization', $organization)
            ->setParameter('status', 'closed')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'count' => $totalCount,
            'pending' => $pendingCount,
            'in_progress' => $inProgressCount,
            'closed' => $closedCount
        ];
    }

    public function save(RoomIssue $issue, bool $flush = false): void
    {
        $this->getEntityManager()->persist($issue);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RoomIssue $issue, bool $flush = false): void
    {
        $this->getEntityManager()->remove($issue);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }
}
