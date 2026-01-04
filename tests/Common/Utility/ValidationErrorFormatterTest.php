<?php

declare(strict_types=1);

namespace App\Tests\Common\Utility;

use App\Common\Utility\ValidationErrorFormatter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

class ValidationErrorFormatterTest extends TestCase
{
    public function testFormatWithNoViolations(): void
    {
        $violations = new ConstraintViolationList();
        
        $result = ValidationErrorFormatter::format($violations);
        
        $this->assertIsArray($result);
        $this->assertEquals(400, $result['code']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertEmpty($result['violations']);
    }

    public function testFormatWithSingleViolation(): void
    {
        $violation = new ConstraintViolation(
            'This field is required',
            null,
            [],
            null,
            'email',
            null
        );
        
        $violations = new ConstraintViolationList([$violation]);
        
        $result = ValidationErrorFormatter::format($violations);
        
        $this->assertIsArray($result);
        $this->assertEquals(400, $result['code']);
        $this->assertEquals('Validation failed', $result['message']);
        $this->assertCount(1, $result['violations']);
        $this->assertEquals('email', $result['violations'][0]['field']);
        $this->assertEquals('This field is required', $result['violations'][0]['message']);
    }

    public function testFormatWithMultipleViolations(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'This field is required',
                null,
                [],
                null,
                'email',
                null
            ),
            new ConstraintViolation(
                'This value is too short',
                null,
                [],
                null,
                'password',
                null
            ),
            new ConstraintViolation(
                'This value is not valid',
                null,
                [],
                null,
                'username',
                null
            )
        ]);
        
        $result = ValidationErrorFormatter::format($violations);
        
        $this->assertIsArray($result);
        $this->assertEquals(400, $result['code']);
        $this->assertCount(3, $result['violations']);
        
        $fields = array_column($result['violations'], 'field');
        $this->assertContains('email', $fields);
        $this->assertContains('password', $fields);
        $this->assertContains('username', $fields);
    }

    public function testFormatStructure(): void
    {
        $violation = new ConstraintViolation(
            'Test message',
            null,
            [],
            null,
            'testField',
            null
        );
        
        $violations = new ConstraintViolationList([$violation]);
        
        $result = ValidationErrorFormatter::format($violations);
        
        $this->assertArrayHasKey('code', $result);
        $this->assertArrayHasKey('message', $result);
        $this->assertArrayHasKey('violations', $result);
        $this->assertIsInt($result['code']);
        $this->assertIsString($result['message']);
        $this->assertIsArray($result['violations']);
    }
}
