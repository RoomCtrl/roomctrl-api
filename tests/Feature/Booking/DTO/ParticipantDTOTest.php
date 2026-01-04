<?php

declare(strict_types=1);

namespace App\Tests\Feature\Booking\DTO;

use App\Feature\Booking\DTO\ParticipantDTO;
use PHPUnit\Framework\TestCase;

class ParticipantDTOTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $id = '123e4567-e89b-12d3-a456-426614174000';
        $username = 'participant1';
        $firstName = 'John';
        $lastName = 'Participant';
        $email = 'john@example.com';
        
        $dto = new ParticipantDTO($id, $username, $firstName, $lastName, $email);
        
        $this->assertEquals($id, $dto->id);
        $this->assertEquals($username, $dto->username);
        $this->assertEquals($firstName, $dto->firstName);
        $this->assertEquals($lastName, $dto->lastName);
        $this->assertEquals($email, $dto->email);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $id = '123e4567-e89b-12d3-a456-426614174000';
        $username = 'test_user';
        $firstName = 'Test';
        $lastName = 'User';
        $email = 'test@example.com';
        
        $dto = new ParticipantDTO($id, $username, $firstName, $lastName, $email);
        $array = $dto->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('username', $array);
        $this->assertArrayHasKey('firstName', $array);
        $this->assertArrayHasKey('lastName', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertEquals($id, $array['id']);
        $this->assertEquals($username, $array['username']);
        $this->assertEquals($firstName, $array['firstName']);
        $this->assertEquals($lastName, $array['lastName']);
        $this->assertEquals($email, $array['email']);
    }

    public function testToArrayContainsAllProperties(): void
    {
        $dto = new ParticipantDTO(
            '123e4567-e89b-12d3-a456-426614174000',
            'participant',
            'First',
            'Last',
            'email@test.com'
        );
        
        $array = $dto->toArray();
        
        $this->assertCount(5, $array);
    }

    public function testWithValidEmailFormats(): void
    {
        $validEmails = [
            'simple@example.com',
            'user.name@example.com',
            'user+tag@example.co.uk',
            'test_email123@subdomain.example.com'
        ];
        
        foreach ($validEmails as $email) {
            $dto = new ParticipantDTO('id', 'username', 'First', 'Last', $email);
            $this->assertEquals($email, $dto->email);
            $this->assertEquals($email, $dto->toArray()['email']);
        }
    }

    public function testWithSpecialCharactersInNames(): void
    {
        $dto = new ParticipantDTO(
            'test-id',
            'josé_maría',
            'José',
            'García-López',
            'jose@example.com'
        );
        
        $this->assertEquals('josé_maría', $dto->username);
        $this->assertEquals('José', $dto->firstName);
        $this->assertEquals('García-López', $dto->lastName);
    }
}
