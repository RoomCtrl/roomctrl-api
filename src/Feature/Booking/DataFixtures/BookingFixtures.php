<?php

declare(strict_types=1);

namespace App\Feature\Booking\DataFixtures;

use App\Feature\Booking\Entity\Booking;
use App\Feature\Organization\DataFixtures\OrganizationFixtures;
use App\Feature\Room\Entity\Room;
use App\Feature\Room\Repository\RoomRepository;
use App\Feature\User\DataFixtures\UserFixtures;
use App\Feature\User\Entity\User;
use App\Feature\User\Repository\UserRepository;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class BookingFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('pl_PL');

        $users = $this->userRepository->findAll();
        $rooms = $this->roomRepository->findAll();

        if (empty($users) || empty($rooms)) {
            return;
        }

        $bookingTitles = [
            'Spotkanie zespołu ds. projektu',
            'Prezentacja quarterly',
            'Planowanie sprintu',
            'Szkolenie z cyberbezpieczeństwa',
            'Workshop - Agile metodyki',
            'Rozmowa kwalifikacyjna',
            'Standup daily',
            'Planning meeting',
            'Code review session',
            'Brainstorming session',
            'Retrospective meeting',
            'Client presentation',
            'Budget review',
            'Training session',
            'Team building activity',
        ];

        $durations = [30, 60, 90, 120, 150, 180];
        $breaks = [0, 15, 30, 60];
        $lastBookingPerRoom = [];

        $bookingCount = 0;

        foreach ($users as $user) {
            $userBookingCount = rand(10, 15);

            for ($j = 0; $j < $userBookingCount; $j++) {
                $userRooms = array_filter($rooms, fn (Room $room) => $room->getOrganization()->getId() === $user->getOrganization()->getId());

                if (empty($userRooms)) {
                    continue;
                }

                $room = $userRooms[array_rand($userRooms)];
                $roomId = (string) $room->getId();

                $booking = new Booking();
                $booking->setTitle($faker->randomElement($bookingTitles) . ' - ' . $faker->word());
                $booking->setRoom($room);
                $booking->setUser($user);

                $duration = $faker->randomElement($durations);

                if (isset($lastBookingPerRoom[$roomId])) {
                    $breakTime = $faker->randomElement($breaks);
                    $startDate = $lastBookingPerRoom[$roomId]->modify(sprintf('+%d minutes', $breakTime));
                } else {
                    $daysOffset = rand(-30, 60);
                    $hour = rand(8, 17);
                    $minute = $faker->randomElement([0, 15, 30, 45]);
                    $startDate = (new \DateTimeImmutable())->modify(sprintf('%d days %d hours %d minutes', $daysOffset, $hour, $minute));
                }

                $endDate = $startDate->modify(sprintf('+%d minutes', $duration));
                $lastBookingPerRoom[$roomId] = $endDate;

                $booking->setStartedAt($startDate);
                $booking->setEndedAt($endDate);

                $booking->setParticipantsCount(rand(2, min(12, $room->getCapacity())));
                $booking->setIsPrivate($faker->boolean(30));

                $now = new \DateTimeImmutable();
                if ($endDate < $now) {
                    $booking->setStatus($faker->randomElement(['completed', 'cancelled']));
                } else {
                    $booking->setStatus('active');
                }

                $manager->persist($booking);
                $bookingCount++;

                if (!$booking->isPrivate() && $bookingCount % 3 === 0) {
                    $participantCount = rand(1, min(3, count($users) - 1));
                    $participantIndices = array_rand($users, $participantCount);

                    if (!is_array($participantIndices)) {
                        $participantIndices = [$participantIndices];
                    }

                    foreach ($participantIndices as $index) {
                        if ($users[$index]->getId() !== $user->getId()) {
                            $booking->addParticipant($users[$index]);
                        }
                    }
                }

                if ($bookingCount % 50 === 0) {
                    $manager->flush();
                }
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            OrganizationFixtures::class,
        ];
    }
}
