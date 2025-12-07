<?php

declare(strict_types=1);

namespace App\Feature\Booking\Repository;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Room\Entity\Room;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Booking>
 */
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
}
