<?php

namespace App\DataFixtures;

use App\Entity\ContactDetail;
use App\Entity\Organization;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Faker\Factory;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('pl_PL');
        
        $organizations = [
            $this->getReference(OrganizationFixtures::ORG_ACME, Organization::class),
            $this->getReference(OrganizationFixtures::ORG_GLOBEX, Organization::class),
            $this->getReference(OrganizationFixtures::ORG_TECH, Organization::class),
        ];

        for ($i = 1; $i <= 10; $i++) {
            $contactDetail = new ContactDetail();
            $contactDetail->setStreetName($faker->streetName());
            $contactDetail->setStreetNumber($faker->buildingNumber());
            $contactDetail->setFlatNumber($faker->optional(0.5)->numerify('##'));
            $contactDetail->setPostCode($faker->postcode());
            $contactDetail->setCity($faker->city());
            $contactDetail->setEmail(sprintf('user%d@roomctrl.com', $i));
            $contactDetail->setPhone($faker->phoneNumber());
            $manager->persist($contactDetail);

            $user = new User();
            $user->setUsername(sprintf('user%d', $i));
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            
            if ($i === 1) {
                $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
            } else {
                $user->setRoles(['ROLE_USER']);
            }
            
            $user->setFirstLoginStatus(false);
            $user->setOrganization($organizations[($i - 1) % 3]);
            $user->setContactDetail($contactDetail);
            
            $hashedPassword = $this->passwordHasher->hashPassword($user, 'P@ssw0rd1');
            $user->setPassword($hashedPassword);
            
            $manager->persist($user);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            OrganizationFixtures::class,
        ];
    }
}
