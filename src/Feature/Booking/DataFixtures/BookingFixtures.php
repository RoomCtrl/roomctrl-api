<?php

declare(strict_types=1);

namespace App\Feature\Booking\DataFixtures;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Room\Entity\Room;
use App\Feature\User\Entity\User;
use App\Feature\Room\DataFixtures\RoomFixtures;
use App\Feature\User\DataFixtures\UserFixtures;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class BookingFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $room201 = $this->getReference(RoomFixtures::ROOM_201_REFERENCE, Room::class);
        $room202 = $this->getReference(RoomFixtures::ROOM_202_REFERENCE, Room::class);
        $room203 = $this->getReference(RoomFixtures::ROOM_203_REFERENCE, Room::class);
        $user = $this->getReference(UserFixtures::USER_REFERENCE, User::class);

        // Current booking for Room 201 (happening now)
        $now = new \DateTimeImmutable();
        $currentBooking201 = new Booking();
        $currentBooking201->setTitle('Spotkanie zespołu ds. projektu Alpha');
        $currentBooking201->setRoom($room201);
        $currentBooking201->setUser($user);
        $currentBooking201->setStartedAt($now->modify('-30 minutes'));
        $currentBooking201->setEndedAt($now->modify('+1 hour'));
        $currentBooking201->setParticipantsCount(8);
        $currentBooking201->setIsPrivate(false);
        $currentBooking201->setStatus('active');

        $manager->persist($currentBooking201);

        // Next booking for Room 201 (in 2 hours)
        $nextBooking201 = new Booking();
        $nextBooking201->setTitle('Prezentacja Q4 2025');
        $nextBooking201->setRoom($room201);
        $nextBooking201->setUser($user);
        $nextBooking201->setStartedAt($now->modify('+2 hours'));
        $nextBooking201->setEndedAt($now->modify('+3 hours 30 minutes'));
        $nextBooking201->setParticipantsCount(12);
        $nextBooking201->setIsPrivate(false);
        $nextBooking201->setStatus('active');

        $manager->persist($nextBooking201);

        // Another next booking for Room 201 (tomorrow morning)
        $tomorrowMorning = new \DateTimeImmutable('tomorrow 09:00');
        $nextBooking201Tomorrow = new Booking();
        $nextBooking201Tomorrow->setTitle('Planowanie sprintu');
        $nextBooking201Tomorrow->setRoom($room201);
        $nextBooking201Tomorrow->setUser($user);
        $nextBooking201Tomorrow->setStartedAt($tomorrowMorning);
        $nextBooking201Tomorrow->setEndedAt($tomorrowMorning->modify('+2 hours'));
        $nextBooking201Tomorrow->setParticipantsCount(10);
        $nextBooking201Tomorrow->setIsPrivate(false);
        $nextBooking201Tomorrow->setStatus('active');

        $manager->persist($nextBooking201Tomorrow);

        // Current booking for Room 202 (training session)
        $currentBooking202 = new Booking();
        $currentBooking202->setTitle('Szkolenie z cyberbezpieczeństwa');
        $currentBooking202->setRoom($room202);
        $currentBooking202->setUser($user);
        $currentBooking202->setStartedAt($now->modify('-1 hour'));
        $currentBooking202->setEndedAt($now->modify('+2 hours'));
        $currentBooking202->setParticipantsCount(18);
        $currentBooking202->setIsPrivate(false);
        $currentBooking202->setStatus('active');

        $manager->persist($currentBooking202);

        // Next booking for Room 202 (this afternoon)
        $afternoon = new \DateTimeImmutable('today 14:00');
        $nextBooking202 = new Booking();
        $nextBooking202->setTitle('Workshop - Agile metodyki');
        $nextBooking202->setRoom($room202);
        $nextBooking202->setUser($user);
        $nextBooking202->setStartedAt($afternoon);
        $nextBooking202->setEndedAt($afternoon->modify('+3 hours'));
        $nextBooking202->setParticipantsCount(15);
        $nextBooking202->setIsPrivate(false);
        $nextBooking202->setStatus('active');

        $manager->persist($nextBooking202);

        // Private meeting in Room 203 (happening soon)
        $soonBooking203 = new Booking();
        $soonBooking203->setTitle('Rozmowa kwalifikacyjna');
        $soonBooking203->setRoom($room203);
        $soonBooking203->setUser($user);
        $soonBooking203->setStartedAt($now->modify('+30 minutes'));
        $soonBooking203->setEndedAt($now->modify('+1 hour 30 minutes'));
        $soonBooking203->setParticipantsCount(3);
        $soonBooking203->setIsPrivate(true);
        $soonBooking203->setStatus('active');

        $manager->persist($soonBooking203);

        // Cancelled booking (example of cancelled status)
        $cancelledBooking = new Booking();
        $cancelledBooking->setTitle('Anulowane spotkanie budżetowe');
        $cancelledBooking->setRoom($room201);
        $cancelledBooking->setUser($user);
        $cancelledBooking->setStartedAt($now->modify('+5 hours'));
        $cancelledBooking->setEndedAt($now->modify('+6 hours'));
        $cancelledBooking->setParticipantsCount(6);
        $cancelledBooking->setIsPrivate(false);
        $cancelledBooking->setStatus('cancelled');

        $manager->persist($cancelledBooking);

        // Completed booking (past meeting)
        $yesterday = new \DateTimeImmutable('yesterday 10:00');
        $completedBooking = new Booking();
        $completedBooking->setTitle('Daily standup');
        $completedBooking->setRoom($room203);
        $completedBooking->setUser($user);
        $completedBooking->setStartedAt($yesterday);
        $completedBooking->setEndedAt($yesterday->modify('+15 minutes'));
        $completedBooking->setParticipantsCount(5);
        $completedBooking->setIsPrivate(false);
        $completedBooking->setStatus('completed');

        $manager->persist($completedBooking);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            RoomFixtures::class,
            UserFixtures::class,
        ];
    }
}
