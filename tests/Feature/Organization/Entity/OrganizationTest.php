<?php

declare(strict_types=1);

namespace App\Tests\Feature\Organization\Entity;

use App\Feature\Organization\Entity\Organization;
use PHPUnit\Framework\TestCase;

class OrganizationTest extends TestCase
{
    public function testOrganizationCreation(): void
    {
        $organization = new Organization();
        
        $this->assertNull($organization->getId());
        $this->assertCount(0, $organization->getUsers());
    }

    public function testSetAndGetRegon(): void
    {
        $organization = new Organization();
        $regon = '123456789';
        
        $result = $organization->setRegon($regon);
        
        $this->assertSame($organization, $result);
        $this->assertEquals($regon, $organization->getRegon());
    }

    public function testSetAndGetName(): void
    {
        $organization = new Organization();
        $name = 'Test Organization';
        
        $result = $organization->setName($name);
        
        $this->assertSame($organization, $result);
        $this->assertEquals($name, $organization->getName());
    }

    public function testSetAndGetEmail(): void
    {
        $organization = new Organization();
        $email = 'test@organization.com';
        
        $result = $organization->setEmail($email);
        
        $this->assertSame($organization, $result);
        $this->assertEquals($email, $organization->getEmail());
    }

    public function testFluentInterface(): void
    {
        $organization = new Organization();
        
        $result = $organization
            ->setRegon('123456789')
            ->setName('Test Org')
            ->setEmail('test@org.com');
        
        $this->assertSame($organization, $result);
        $this->assertEquals('123456789', $organization->getRegon());
        $this->assertEquals('Test Org', $organization->getName());
        $this->assertEquals('test@org.com', $organization->getEmail());
    }
}
