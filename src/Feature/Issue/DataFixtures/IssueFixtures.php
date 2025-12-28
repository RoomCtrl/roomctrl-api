<?php

declare(strict_types=1);

namespace App\Feature\Issue\DataFixtures;

use App\Feature\Issue\Entity\IssueHistory;
use App\Feature\Issue\Entity\IssueNote;
use App\Feature\Issue\Entity\RoomIssue;
use App\Feature\Organization\DataFixtures\OrganizationFixtures;
use App\Feature\Organization\Entity\Organization;
use App\Feature\Room\DataFixtures\RoomFixtures;
use App\Feature\Room\Entity\Room;
use App\Feature\User\DataFixtures\UserFixtures;
use App\Feature\User\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class IssueFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public static function getGroups(): array
    {
        return ['issue'];
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('pl_PL');

        $categories = ['equipment', 'infrastructure', 'furniture'];
        $statuses = ['pending', 'in_progress', 'closed'];
        $priorities = ['low', 'medium', 'high', 'critical'];

        $descriptions = [
            'equipment' => [
                'Nie działa projektor - brak obrazu. Po włączeniu zasilania urządzenie nie reaguje, dioda zasilania nie świeci.',
                'Połamane krzesło nr 12. Jedno z nóg krzesła jest uszkodzone, krzesło jest niebezpieczne do użytku.',
                'Brak obrazu na monitorze. Monitor nie wyświetla obrazu, sprawdzono przewód zasilający - jest prawidłowy.',
                'Projektor działa ale obraz jest nieostry i zniekształcony.',
                'Mysz komputerowa nie reaguje na kliknięcia, wymaga wymiany.',
                'Klimatyzacja nie chłodzi pomieszczenia pomimo włączenia.',
            ],
            'infrastructure' => [
                'Przeciek w suficie przy oknie. Widoczne ślady wilgoci i przebarwienia. W czasie deszczu kapie woda.',
                'Uszkodzone gniazdko elektryczne przy biurku - wymaga naprawy przez elektryka.',
                'Nie działa oświetlenie - wszystkie żarówki wymagają wymiany.',
                'Przeciek wody pod umywalką w toalecie.',
                'Okno się nie zamyka prawidłowo, problem z klamką.',
            ],
            'furniture' => [
                'Połamane krzesło nr 12. Jedno z nóg krzesła jest uszkodzone, krzesło jest niebezpieczne do użytku.',
                'Szafa uchylna się od ściany, wymaga zamocowania.',
                'Uszkodzony blat stołu - odkleiła się okleina.',
                'Szuflada w biurku się zacina, nie można jej otworzyć.',
            ]
        ];

        $org1 = $this->getReference(OrganizationFixtures::ORG_ACME, Organization::class);
        $org2 = $this->getReference(OrganizationFixtures::ORG_GLOBEX, Organization::class);
        $org3 = $this->getReference(OrganizationFixtures::ORG_TECH, Organization::class);

        $organizations = [$org1, $org2, $org3];

        for ($i = 1; $i <= 15; $i++) {
            $orgIndex = ($i - 1) % 3;
            $organization = $organizations[$orgIndex];

            $roomRefs = ['room-201', 'room-202', 'room-203', 'room-301'];
            $roomRef = $roomRefs[($i - 1) % 4];
            $room = $this->getReference($roomRef, Room::class);

            if ($roomRef === 'room-301') {
                $userIndex = 34 + (($i - 1) % 32);
            } else {
                $userIndex = 1 + (($i - 1) % 33);
            }
            $reporter = $this->getReference("user-{$userIndex}", User::class);

            $organization = $room->getOrganization();

            $category = $categories[array_rand($categories)];
            $status = $statuses[array_rand($statuses)];
            $priority = $priorities[array_rand($priorities)];

            $issue = new RoomIssue();
            $issue->setRoom($room);
            $issue->setReporter($reporter);
            $issue->setOrganization($organization);
            $issue->setCategory($category);
            $issue->setDescription($descriptions[$category][array_rand($descriptions[$category])]);
            $issue->setStatus($status);
            $issue->setPriority($priority);

            $reportedAt = $faker->dateTimeBetween('-30 days', '-1 day');
            $issue->setReportedAt(DateTimeImmutable::createFromMutable($reportedAt));

            if ($status === 'closed') {
                $closedAt = $faker->dateTimeBetween($reportedAt, 'now');
                $issue->setClosedAt(DateTimeImmutable::createFromMutable($closedAt));
            }

            $history1 = new IssueHistory();
            $history1->setIssue($issue);
            $history1->setUser($reporter);
            $history1->setAction('created');
            $history1->setDescription('Issue was created');
            $history1->setCreatedAt($issue->getReportedAt());
            $issue->addHistory($history1);

            if ($status === 'in_progress' || $status === 'closed') {
                $history2 = new IssueHistory();
                $history2->setIssue($issue);
                
                $admin = $this->getReference("user-1", User::class);
                
                $history2->setUser($admin);
                $history2->setAction('status_changed');
                $history2->setDescription("Status changed to 'in_progress'");
                $changedAt = $faker->dateTimeBetween($reportedAt, 'now');
                $history2->setCreatedAt(DateTimeImmutable::createFromMutable($changedAt));
                $issue->addHistory($history2);

                if ($faker->boolean(70)) {
                    $note = new IssueNote();
                    $note->setIssue($issue);
                    $note->setAuthor($admin);
                    $note->setContent($faker->randomElement([
                        'Sprawdzono przewód zasilający - jest prawidłowy.',
                        'Zamówiono nowe części zamienne.',
                        'Przekazano zlecenie do działu technicznego.',
                        'Wymieniono uszkodzony element.',
                        'Problem rozwiązany, wymieniono całe urządzenie.',
                    ]));
                    $noteCreatedAt = $faker->dateTimeBetween($changedAt, 'now');
                    $note->setCreatedAt(DateTimeImmutable::createFromMutable($noteCreatedAt));
                    $issue->addNote($note);

                    $historyNote = new IssueHistory();
                    $historyNote->setIssue($issue);
                    $historyNote->setUser($admin);
                    $historyNote->setAction('note_added');
                    $historyNote->setDescription('Service note was added');
                    $historyNote->setCreatedAt($note->getCreatedAt());
                    $issue->addHistory($historyNote);
                }
            }

            if ($status === 'closed') {
                $history3 = new IssueHistory();
                $history3->setIssue($issue);
                $admin = $this->getReference("user-1", User::class);
                $history3->setUser($admin);
                $history3->setAction('closed');
                $history3->setDescription('Issue was closed');
                $history3->setCreatedAt($issue->getClosedAt());
                $issue->addHistory($history3);
            }

            $manager->persist($issue);
            $this->addReference("issue-{$i}", $issue);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            OrganizationFixtures::class,
            UserFixtures::class,
            RoomFixtures::class,
        ];
    }
}
