<?php

declare(strict_types=1);

namespace App\Feature\Booking\Repository;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;
use DateTimeImmutable;

class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    public function save(Booking $booking, bool $flush = false): void
    {
        $this->getEntityManager()->persist($booking);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Booking $booking, bool $flush = false): void
    {
        $this->getEntityManager()->remove($booking);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findById(Uuid $id): ?Booking
    {
        return $this->find($id);
    }

    public function findByCriteria(array $criteria, array $orderBy = []): array
    {
        return $this->findBy($criteria, $orderBy);
    }

    public function findConflictingBooking(
        Room $room,
        DateTimeImmutable $startedAt,
        DateTimeImmutable $endedAt,
        ?Uuid $excludeBookingId = null
    ): ?Booking {
        $qb = $this->createQueryBuilder('b')
            ->where('b.room = :room')
            ->andWhere('b.status = :status')
            ->andWhere('(b.startedAt < :endedAt AND b.endedAt > :startedAt)')
            ->setParameter('room', $room)
            ->setParameter('status', 'active')
            ->setParameter('startedAt', $startedAt)
            ->setParameter('endedAt', $endedAt)
            ->setMaxResults(1);

        if ($excludeBookingId) {
            $qb->andWhere('b.id != :excludeId')
                ->setParameter('excludeId', $excludeBookingId, 'uuid');
        }

        $results = $qb->getQuery()->getResult();
        return !empty($results) ? $results[0] : null;
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
    }

    public function findByUserOrParticipant(User $user): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.participants', 'p')
            ->where('b.user = :user')
            ->orWhere('p = :user')
            ->setParameter('user', $user)
            ->orderBy('b.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getBookingCountsByUser(User $user): array
    {
        $qb = $this->createQueryBuilder('b');

        $totalCount = (int) $qb
            ->select('COUNT(b.id)')
            ->where('b.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $activeCount = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.user = :user')
            ->andWhere('b.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $completedCount = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.user = :user')
            ->andWhere('b.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $cancelledCount = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.user = :user')
            ->andWhere('b.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'count' => $totalCount,
            'active' => $activeCount,
            'completed' => $completedCount,
            'cancelled' => $cancelledCount
        ];
    }

    public function getBookingCountsByOrganization(Organization $organization): array
    {
        $qb = $this->createQueryBuilder('b');

        $totalCount = (int) $qb
            ->select('COUNT(b.id)')
            ->join('b.user', 'u')
            ->where('u.organization = :organization')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getSingleScalarResult();

        $activeCount = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.user', 'u')
            ->where('u.organization = :organization')
            ->andWhere('b.status = :status')
            ->setParameter('organization', $organization)
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $completedCount = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.user', 'u')
            ->where('u.organization = :organization')
            ->andWhere('b.status = :status')
            ->setParameter('organization', $organization)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $cancelledCount = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.user', 'u')
            ->where('u.organization = :organization')
            ->andWhere('b.status = :status')
            ->setParameter('organization', $organization)
            ->setParameter('status', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'count' => $totalCount,
            'active' => $activeCount,
            'completed' => $completedCount,
            'cancelled' => $cancelledCount
        ];
    }

    public function getTotalBookingStats(Organization $organization): array
    {
        $now = new DateTimeImmutable();
        $startOfMonth = new DateTimeImmutable('first day of this month 00:00:00');
        $startOfWeek = new DateTimeImmutable('monday this week 00:00:00');
        $startOfDay = new DateTimeImmutable('today 00:00:00');

        $totalCount = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.user', 'u')
            ->where('u.organization = :organization')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getSingleScalarResult();

        $thisMonthCount = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.user', 'u')
            ->where('u.organization = :organization')
            ->andWhere('b.createdAt >= :startOfMonth')
            ->setParameter('organization', $organization)
            ->setParameter('startOfMonth', $startOfMonth)
            ->getQuery()
            ->getSingleScalarResult();

        $thisWeekCount = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.user', 'u')
            ->where('u.organization = :organization')
            ->andWhere('b.createdAt >= :startOfWeek')
            ->setParameter('organization', $organization)
            ->setParameter('startOfWeek', $startOfWeek)
            ->getQuery()
            ->getSingleScalarResult();

        $todayCount = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.user', 'u')
            ->where('u.organization = :organization')
            ->andWhere('b.createdAt >= :startOfDay')
            ->setParameter('organization', $organization)
            ->setParameter('startOfDay', $startOfDay)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $totalCount,
            'thisMonth' => $thisMonthCount,
            'thisWeek' => $thisWeekCount,
            'today' => $todayCount
        ];
    }

    public function getOccupancyRateByDayOfWeek(Organization $organization): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT 
                EXTRACT(DOW FROM b.started_at) as day_of_week,
                COUNT(b.id) as booking_count,
                SUM(EXTRACT(EPOCH FROM (b.ended_at - b.started_at)) / 3600) as total_hours
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            WHERE u.organization_id = :organization_id
              AND b.status IN (:status_active, :status_completed)
            GROUP BY day_of_week
        ';

        $result = $conn->executeQuery($sql, [
            'organization_id' => $organization->getId()->toRfc4122(),
            'status_active' => 'active',
            'status_completed' => 'completed',
        ])->fetchAllAssociative();

        $totalRooms = (int) $this->getEntityManager()
            ->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from(Room::class, 'r')
            ->where('r.organization = :organization')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getSingleScalarResult();

        $dayMapping = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            0 => 'sunday'
        ];

        $occupancyData = [];
        foreach ($dayMapping as $pgDay => $dayName) {
            $occupancyData[$dayName] = 0.0;
        }

        $availableHoursPerDay = $totalRooms * 12;

        foreach ($result as $row) {
            $dayOfWeek = (int) $row['day_of_week'];
            $totalHours = (float) ($row['total_hours'] ?? 0);

            if (isset($dayMapping[$dayOfWeek]) && $availableHoursPerDay > 0) {
                $dayName = $dayMapping[$dayOfWeek];
                $occupancyRate = ($totalHours / $availableHoursPerDay) * 100;
                $occupancyData[$dayName] = min($occupancyRate, 100);
            }
        }

        return $occupancyData;
    }

    public function findByOrganization(Organization $organization): array
    {
        return $this->createQueryBuilder('b')
            ->join('b.user', 'u')
            ->where('u.organization = :organization')
            ->setParameter('organization', $organization)
            ->getQuery()
            ->getResult();
    }
}
