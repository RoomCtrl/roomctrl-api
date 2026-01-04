<?php

declare(strict_types=1);

namespace App\Feature\Room\Repository;

use App\Feature\Room\Entity\Room;
use App\Feature\Organization\Entity\Organization;
use App\Feature\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use DateTimeImmutable;

/**
 * @extends ServiceEntityRepository<Room>
 */
class RoomRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Room::class);
    }

    public function save(Room $room, bool $flush = false): void
    {
        $this->getEntityManager()->persist($room);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Room $room, bool $flush = false): void
    {
        $this->getEntityManager()->remove($room);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function findById(Uuid $id): ?Room
    {
        return $this->find($id);
    }

    public function findAll(): array
    {
        return parent::findAll();
    }

    /**
     * @return Room[]
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.roomStatus', 'rs')
            ->where('rs.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Room[]
     */
    public function findByOrganizationAndStatus(Organization $organization, string $status): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.roomStatus', 'rs')
            ->where('r.organization = :organization')
            ->andWhere('rs.status = :status')
            ->setParameter('organization', $organization)
            ->setParameter('status', $status)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get user's favorite rooms
     *
     * @return Room[]
     */
    public function findFavoritesByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.favoritedByUsers', 'u')
            ->where('u.id = :userId')
            ->setParameter('userId', $user->getId())
            ->getQuery()
            ->getResult();
    }

    public function getMostUsedRooms(Organization $organization, int $limit = 5): array
    {
        $oneWeekAgo = new DateTimeImmutable('-1 week');
        $oneMonthAgo = new DateTimeImmutable('-1 month');

        return $this->createQueryBuilder('r')
            ->select(
                'r.id',
                'r.roomName',
                'COUNT(b.id) as bookingCount',
                'SUM(CASE WHEN b.startedAt >= :oneWeekAgo THEN 1 ELSE 0 END) as weeklyBookings',
                'SUM(CASE WHEN b.startedAt >= :oneMonthAgo THEN 1 ELSE 0 END) as monthlyBookings'
            )
            ->leftJoin('r.bookings', 'b')
            ->where('r.organization = :organization')
            ->setParameter('organization', $organization)
            ->setParameter('oneWeekAgo', $oneWeekAgo)
            ->setParameter('oneMonthAgo', $oneMonthAgo)
            ->groupBy('r.id')
            ->orderBy('bookingCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getLeastUsedRooms(Organization $organization, int $limit = 5): array
    {
        $oneWeekAgo = new DateTimeImmutable('-1 week');
        $oneMonthAgo = new DateTimeImmutable('-1 month');

        return $this->createQueryBuilder('r')
            ->select(
                'r.id',
                'r.roomName',
                'COUNT(b.id) as bookingCount',
                'SUM(CASE WHEN b.startedAt >= :oneWeekAgo THEN 1 ELSE 0 END) as weeklyBookings',
                'SUM(CASE WHEN b.startedAt >= :oneMonthAgo THEN 1 ELSE 0 END) as monthlyBookings'
            )
            ->leftJoin('r.bookings', 'b')
            ->where('r.organization = :organization')
            ->setParameter('organization', $organization)
            ->setParameter('oneWeekAgo', $oneWeekAgo)
            ->setParameter('oneMonthAgo', $oneMonthAgo)
            ->groupBy('r.id')
            ->orderBy('bookingCount', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getRoomsWithMostIssues(Organization $organization, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.id', 'r.roomName', 'COUNT(i.id) as issueCount')
            ->leftJoin('r.issues', 'i')
            ->where('r.organization = :organization')
            ->setParameter('organization', $organization)
            ->groupBy('r.id')
            ->having('COUNT(i.id) > 0')
            ->orderBy('issueCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function getTotalBookingsCount(Organization $organization): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(b.id)')
            ->leftJoin('r.bookings', 'b')
            ->where('r.organization = :organization')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
