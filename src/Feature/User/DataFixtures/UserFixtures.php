<?php

declare(strict_types=1);

namespace App\Feature\User\DataFixtures;

use App\Feature\Organization\DataFixtures\OrganizationFixtures;
use App\Feature\Organization\Entity\Organization;
use App\Feature\User\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public const string USER_REFERENCE = 'user-1';

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

        for ($i = 1; $i <= 100; $i++) {
            $user = new User();
            $user->setUsername(sprintf('user%d', $i));
            $user->setFirstName($faker->firstName());
            $user->setLastName($faker->lastName());
            $user->setEmail(sprintf('user%d@roomctrl.com', $i));
            $user->setPhone($faker->phoneNumber());

            $organizationIndex = ($i - 1) % 3;
            $user->setOrganization($organizations[$organizationIndex]);

            if ($i === 1 || $i === 34 || $i === 67) {
                $user->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
            } else {
                $user->setRoles(['ROLE_USER']);
            }

            // Deaktywuj co 10. użytkownika dla testów
            if ($i % 10 === 0) {
                $user->setIsActive(false);
            } else {
                $user->setIsActive(true);
            }

            $hashedPassword = $this->passwordHasher->hashPassword($user, 'P@ssw0rd1');
            $user->setPassword($hashedPassword);

            $manager->persist($user);

            if ($i === 1) {
                $this->addReference(self::USER_REFERENCE, $user);
            } else {
                $this->addReference(sprintf('user-%d', $i), $user);
            }
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
