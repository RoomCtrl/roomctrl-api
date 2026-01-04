<?php

declare(strict_types=1);

namespace App\Tests\Feature\Booking\DTO;

use App\Feature\Booking\DTO\UserDTO;
use PHPUnit\Framework\TestCase;

class UserDTOTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $id = '123e4567-e89b-12d3-a456-426614174000';
        $username = 'john_doe';
        $firstName = 'John';
        $lastName = 'Doe';
        
        $dto = new UserDTO($id, $username, $firstName, $lastName);
        
        $this->assertEquals($id, $dto->id);
        $this->assertEquals($username, $dto->username);
        $this->assertEquals($firstName, $dto->firstName);
        $this->assertEquals($lastName, $dto->lastName);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $id = '123e4567-e89b-12d3-a456-426614174000';
        $username = 'jane_smith';
        $firstName = 'Jane';
        $lastName = 'Smith';
        
        $dto = new UserDTO($id, $username, $firstName, $lastName);
        $array = $dto->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('username', $array);
        $this->assertArrayHasKey('firstName', $array);
        $this->assertArrayHasKey('lastName', $array);
        $this->assertEquals($id, $array['id']);
        $this->assertEquals($username, $array['username']);
        $this->assertEquals($firstName, $array['firstName']);
        $this->assertEquals($lastName, $array['lastName']);
    }

    public function testToArrayContainsAllProperties(): void
    {
        $dto = new UserDTO(
            '123e4567-e89b-12d3-a456-426614174000',
            'testuser',
            'Test',
            'User'
        );
        
        $array = $dto->toArray();
        
        $this->assertCount(4, $array);
    }

    public function testWithSpecialCharacters(): void
    {
        $dto = new UserDTO(
            'test-id',
            'user_ñáme',
            'François',
            'O\'Brien'
        );
        
        $this->assertEquals('user_ñáme', $dto->username);
        $this->assertEquals('François', $dto->firstName);
        $this->assertEquals('O\'Brien', $dto->lastName);
    }
}
