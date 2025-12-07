<?php

declare(strict_types=1);

namespace App\Feature\Booking\Repository;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $endedAt,
        ?Uuid $excludeBookingId = null
    ): ?Booking {
        $qb = $this->createQueryBuilder('b')
            ->where('b.room = :room')
            ->andWhere('b.status = :status')
            ->andWhere('(b.startedAt < :endedAt AND b.endedAt > :startedAt)')
            ->setParameter('room', $room)
            ->setParameter('status', 'active')
            ->setParameter('startedAt', $startedAt)
            ->setParameter('endedAt', $endedAt);

        if ($excludeBookingId) {
            $qb->andWhere('b.id != :excludeId')
                ->setParameter('excludeId', $excludeBookingId, 'uuid');
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    public function flush(): void
    {
        $this->getEntityManager()->flush();
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
}
