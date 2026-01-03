<?php

declare(strict_types=1);

namespace App\Feature\Organization\DataFixtures;

use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Equipment;
use App\Feature\Room\Entity\Room;
use App\Feature\Room\Entity\RoomStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class OrganizationFixtures extends Fixture
{
    public const string ORG_ACME = 'org-acme';
    public const string ORG_GLOBEX = 'org-globex';
    public const string ORG_TECH = 'org-tech';

    private array $equipmentNames = [
        'video' => ['Projektor Full HD', 'Projektor 4K', 'TV 55"', 'TV 75"', 'Ekran interaktywny', 'Kamera konferencyjna'],
        'audio' => ['System audio', 'Mikrofon bezprzewodowy', 'Głośniki', 'Mikrofon podkładowy', 'Nagłośnienie'],
        'computer' => ['Laptop prezentacyjny', 'Komputer stacjonarny', 'Monitor 27"', 'Monitor 32"', 'MacBook Pro'],
        'accessory' => ['Tablica interaktywna', 'Tablica magnetyczna', 'Flipchart', 'Wskaźnik laserowy', 'Tablica biała'],
        'furniture' => ['Krzesła konferencyjne', 'Stół konferencyjny', 'Biurka szkoleniowe', 'Fotel biurowy', 'Stół owalny', 'Szafki'],
    ];

    private array $roomTypes = [
        ['name' => 'Sala Konferencyjna', 'capacity' => 12, 'size' => 45.5, 'access' => 'Karta magnetyczna'],
        ['name' => 'Sala Szkoleniowa', 'capacity' => 20, 'size' => 60.0, 'access' => 'Karta magnetyczna'],
        ['name' => 'Sala Spotkań', 'capacity' => 6, 'size' => 20.0, 'access' => 'Kod PIN'],
        ['name' => 'Gabinet', 'capacity' => 4, 'size' => 18.0, 'access' => 'Klucz'],
        ['name' => 'Sala Wideo', 'capacity' => 30, 'size' => 75.0, 'access' => 'Karta magnetyczna'],
    ];

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('pl_PL');

        $org1 = new Organization();
        $org1->setRegon('123456789');
        $org1->setName('Acme Corporation');
        $org1->setEmail('contact@acme.com');
        $manager->persist($org1);
        $this->addReference(self::ORG_ACME, $org1);

        $org2 = new Organization();
        $org2->setRegon('987654321');
        $org2->setName('Globex Ltd');
        $org2->setEmail('info@globex.com');
        $manager->persist($org2);
        $this->addReference(self::ORG_GLOBEX, $org2);

        $org3 = new Organization();
        $org3->setRegon('555666777');
        $org3->setName('Tech Solutions Sp. z o.o.');
        $org3->setEmail('biuro@techsolutions.pl');
        $manager->persist($org3);
        $this->addReference(self::ORG_TECH, $org3);

        $manager->flush();

        foreach ([$org1, $org2, $org3] as $org) {
            $this->createRoomsForOrganization($manager, $org, $faker);
        }

        $manager->flush();
    }

    private function createRoomsForOrganization(ObjectManager $manager, Organization $org, $faker): void
    {
        for ($i = 1; $i <= 50; $i++) {
            $roomType = $this->roomTypes[($i - 1) % count($this->roomTypes)];
            $floor = (int)($i / 10) + 1;
            $roomNumber = ($floor * 100) + ($i % 10);

            $room = new Room();
            $room->setRoomName(sprintf('%s %d', $roomType['name'], $roomNumber));
            $room->setCapacity($roomType['capacity']);
            $room->setSize($roomType['size']);
            $room->setLocation(sprintf('Piętro %d, Skrzydło %s', $floor, chr(65 + (($i - 1) % 3))));
            $room->setAccess($roomType['access']);
            $room->setDescription($faker->sentence(10));
            $room->setLighting($faker->randomElement(['natural', 'led', 'fluorescent']));
            $room->setAirConditioning(['min' => 18 + rand(0, 2), 'max' => 24 + rand(0, 2)]);
            $room->setOrganization($org);

            $status = new RoomStatus();
            $status->setStatus($faker->randomElement(['available', 'out_of_use']));
            $status->setRoom($room);
            $room->setRoomStatus($status);

            $equipmentCategories = array_keys($this->equipmentNames);
            $equipmentCount = rand(3, 6);
            $selectedCategories = array_rand($equipmentCategories, min($equipmentCount, count($equipmentCategories)));

            if (!is_array($selectedCategories)) {
                $selectedCategories = [$selectedCategories];
            }

            foreach ($selectedCategories as $categoryIndex) {
                $category = $equipmentCategories[$categoryIndex];
                $equipmentName = $this->equipmentNames[$category][array_rand($this->equipmentNames[$category])];

                $equipment = new Equipment();
                $equipment->setName($equipmentName);
                $equipment->setCategory($category);
                $equipment->setQuantity(match ($category) {
                    'furniture' => rand(1, 12),
                    'computer' => rand(1, 3),
                    default => 1,
                });
                $equipment->setRoom($room);

                $manager->persist($equipment);
            }

            $manager->persist($room);
        }
    }
}
