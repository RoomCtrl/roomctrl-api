<?php

declare(strict_types=1);

namespace App\Feature\Room\DataFixtures;

use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\Entity\Equipment;
use App\Feature\Room\Entity\Room;
use App\Feature\Room\Entity\RoomStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use App\Feature\Organization\DataFixtures\OrganizationFixtures;

class RoomFixtures extends Fixture implements DependentFixtureInterface
{
    public const ROOM_201_REFERENCE = 'room-201';
    public const ROOM_202_REFERENCE = 'room-202';
    public const ROOM_203_REFERENCE = 'room-203';
    public const ROOM_301_REFERENCE = 'room-301';

    public function load(ObjectManager $manager): void
    {
        $organization = $this->getReference(OrganizationFixtures::ORG_ACME, Organization::class);
        
        $room201 = new Room();
        $room201->setRoomName('Sala Konferencyjna 201');
        $room201->setCapacity(12);
        $room201->setSize(45.5);
        $room201->setLocation('Piętro 2, Skrzydło A');
        $room201->setAccess('Karta magnetyczna');
        $room201->setDescription('Przestronna sala konferencyjna z naturalnym oświetleniem, idealna na spotkania zespołowe i prezentacje.');
        $room201->setLighting('natural');
        $room201->setAirConditioning(['min' => 18, 'max' => 24]);
        $room201->setOrganization($organization);
        
        $status201 = new RoomStatus();
        $status201->setStatus('available');
        $status201->setRoom($room201);
        $room201->setRoomStatus($status201);
        
        $projector201 = new Equipment();
        $projector201->setName('Projektor Full HD');
        $projector201->setCategory('video');
        $projector201->setQuantity(1);
        $projector201->setRoom($room201);

        $whiteboard201 = new Equipment();
        $whiteboard201->setName('Tablica interaktywna');
        $whiteboard201->setCategory('accessory');
        $whiteboard201->setQuantity(1);
        $whiteboard201->setRoom($room201);

        $speakers201 = new Equipment();
        $speakers201->setName('System audio');
        $speakers201->setCategory('audio');
        $speakers201->setQuantity(1);
        $speakers201->setRoom($room201);

        $chairs201 = new Equipment();
        $chairs201->setName('Krzesła konferencyjne');
        $chairs201->setCategory('furniture');
        $chairs201->setQuantity(12);
        $chairs201->setRoom($room201);

        $table201 = new Equipment();
        $table201->setName('Stół konferencyjny');
        $table201->setCategory('furniture');
        $table201->setQuantity(1);
        $table201->setRoom($room201);

        $manager->persist($room201);
        $manager->persist($projector201);
        $manager->persist($whiteboard201);
        $manager->persist($speakers201);
        $manager->persist($chairs201);
        $manager->persist($table201);
        
        $room202 = new Room();
        $room202->setRoomName('Sala Szkoleniowa 202');
        $room202->setCapacity(20);
        $room202->setSize(60.0);
        $room202->setLocation('Piętro 2, Skrzydło A');
        $room202->setAccess('Karta magnetyczna');
        $room202->setDescription('Duża sala szkoleniowa z nowoczesnym wyposażeniem, przystosowana do szkoleń i warsztatów.');
        $room202->setLighting('led');
        $room202->setAirConditioning(['min' => 20, 'max' => 26]);
        $room202->setOrganization($organization);
        
        $status202 = new RoomStatus();
        $status202->setStatus('available');
        $status202->setRoom($room202);
        $room202->setRoomStatus($status202);
        
        $projector202 = new Equipment();
        $projector202->setName('Projektor 4K');
        $projector202->setCategory('video');
        $projector202->setQuantity(1);
        $projector202->setRoom($room202);

        $microphone202 = new Equipment();
        $microphone202->setName('Mikrofon bezprzewodowy');
        $microphone202->setCategory('audio');
        $microphone202->setQuantity(2);
        $microphone202->setRoom($room202);

        $laptop202 = new Equipment();
        $laptop202->setName('Laptop prezentacyjny');
        $laptop202->setCategory('computer');
        $laptop202->setQuantity(1);
        $laptop202->setRoom($room202);

        $desks202 = new Equipment();
        $desks202->setName('Biurka szkoleniowe');
        $desks202->setCategory('furniture');
        $desks202->setQuantity(10);
        $desks202->setRoom($room202);

        $flipchart202 = new Equipment();
        $flipchart202->setName('Flipchart');
        $flipchart202->setCategory('accessory');
        $flipchart202->setQuantity(2);
        $flipchart202->setRoom($room202);

        $manager->persist($room202);
        $manager->persist($projector202);
        $manager->persist($microphone202);
        $manager->persist($laptop202);
        $manager->persist($desks202);
        $manager->persist($flipchart202);
        
        $room203 = new Room();
        $room203->setRoomName('Sala Spotkań 203');
        $room203->setCapacity(6);
        $room203->setSize(20.0);
        $room203->setLocation('Piętro 2, Skrzydło A');
        $room203->setAccess('Kod PIN');
        $room203->setDescription('Kameralna sala idealna na małe spotkania zespołowe i ciche rozmowy.');
        $room203->setLighting('natural');
        $room203->setAirConditioning(['min' => 19, 'max' => 23]);
        $room203->setOrganization($organization);
        
        $status203 = new RoomStatus();
        $status203->setStatus('available');
        $status203->setRoom($room203);
        $room203->setRoomStatus($status203);
        
        $tv203 = new Equipment();
        $tv203->setName('TV 55"');
        $tv203->setCategory('video');
        $tv203->setQuantity(1);
        $tv203->setRoom($room203);

        $table203 = new Equipment();
        $table203->setName('Stół owalny');
        $table203->setCategory('furniture');
        $table203->setQuantity(1);
        $table203->setRoom($room203);

        $chairs203 = new Equipment();
        $chairs203->setName('Krzesła biurowe');
        $chairs203->setCategory('furniture');
        $chairs203->setQuantity(6);
        $chairs203->setRoom($room203);

        $whiteboard203 = new Equipment();
        $whiteboard203->setName('Tablica magnetyczna');
        $whiteboard203->setCategory('accessory');
        $whiteboard203->setQuantity(1);
        $whiteboard203->setRoom($room203);

        $manager->persist($room203);
        $manager->persist($tv203);
        $manager->persist($table203);
        $manager->persist($chairs203);
        $manager->persist($whiteboard203);
        
        $room301 = new Room();
        $room301->setRoomName('Gabinet 301');
        $room301->setCapacity(4);
        $room301->setSize(18.0);
        $room301->setLocation('Piętro 3, Skrzydło B');
        $room301->setAccess('Klucz');
        $room301->setDescription('Prywatny gabinet z biurkiem i miejscem na konsultacje.');
        $room301->setLighting('led');
        $room301->setAirConditioning(['min' => 20, 'max' => 24]);
        $room301->setOrganization($organization);
        
        $status301 = new RoomStatus();
        $status301->setStatus('out_of_use');
        $status301->setRoom($room301);
        $room301->setRoomStatus($status301);
        
        $desk301 = new Equipment();
        $desk301->setName('Biurko kierownicze');
        $desk301->setCategory('furniture');
        $desk301->setQuantity(1);
        $desk301->setRoom($room301);

        $chair301 = new Equipment();
        $chair301->setName('Fotel biurowy');
        $chair301->setCategory('furniture');
        $chair301->setQuantity(1);
        $chair301->setRoom($room301);

        $computer301 = new Equipment();
        $computer301->setName('Komputer stacjonarny');
        $computer301->setCategory('computer');
        $computer301->setQuantity(1);
        $computer301->setRoom($room301);

        $monitor301 = new Equipment();
        $monitor301->setName('Monitor 27"');
        $monitor301->setCategory('computer');
        $monitor301->setQuantity(2);
        $monitor301->setRoom($room301);

        $manager->persist($room301);
        $manager->persist($desk301);
        $manager->persist($chair301);
        $manager->persist($computer301);
        $manager->persist($monitor301);

        $manager->flush();
        
        $this->addReference(self::ROOM_201_REFERENCE, $room201);
        $this->addReference(self::ROOM_202_REFERENCE, $room202);
        $this->addReference(self::ROOM_203_REFERENCE, $room203);
        $this->addReference(self::ROOM_301_REFERENCE, $room301);
    }

    public function getDependencies(): array
    {
        return [
            OrganizationFixtures::class,
        ];
    }
}
