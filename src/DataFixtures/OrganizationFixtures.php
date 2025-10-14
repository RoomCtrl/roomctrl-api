<?php

namespace App\DataFixtures;

use App\Entity\Organization;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class OrganizationFixtures extends Fixture
{
    public const ORG_ACME = 'org-acme';
    public const ORG_GLOBEX = 'org-globex';
    public const ORG_TECH = 'org-tech';

    public function load(ObjectManager $manager): void
    {
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
    }
}
