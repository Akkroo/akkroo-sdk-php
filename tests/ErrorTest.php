<?php
namespace Akkroo\Tests;

use PHPUnit\Framework\TestCase;

use Akkroo\Error;

class ErrorTest extends TestCase
{
    public function testGenericError()
    {
        $error = new Error\Generic();
        $this->assertInstanceOf(Error\Generic::class, $error);
        $this->assertEquals(400, $error->getCode());
        $this->assertEquals('Bad Request', $error->getMessage());
    }

    public function testNotFoundError()
    {
        $error = new Error\NotFound();
        $this->assertInstanceOf(Error\Generic::class, $error);
        $this->assertEquals(404, $error->getCode());
        $this->assertEquals('Resource Not Found', $error->getMessage());
    }

    public function testAuthenticationError()
    {
        $error = new Error\Authentication();
        $this->assertInstanceOf(Error\Authentication::class, $error);
        $this->assertEquals(401, $error->getCode());
        $this->assertEquals('Unauthorized', $error->getMessage());

        $error = new Error\Authentication('Forbidden', 403);
        $this->assertInstanceOf(Error\Authentication::class, $error);
        $this->assertEquals(403, $error->getCode());
        $this->assertEquals('Forbidden', $error->getMessage());
    }

    public function testValidationError()
    {
        $error = new Error\Validation();
        $this->assertInstanceOf(Error\Validation::class, $error);
        $this->assertEquals(400, $error->getCode());
        $this->assertEquals('Validation Error', $error->getMessage());

        $error = new Error\Validation('Invalid Input data', 400, [
            'data' => [
                'message' => 'Custom error message',
                'details' => [
                    'errors' => [
                        [
                            'attribute' => 'someField',
                            'type' => 'someErrorType',
                            'parameters' => ['value' => 'someValue']
                        ]
                    ]
                ]
            ]
        ]);
        $this->assertInstanceOf(Error\Validation::class, $error);
        $this->assertEquals(400, $error->getCode());
        $this->assertEquals('Custom error message', $error->getMessage());
        $errorDetails = $error->getDetails();
        $this->assertInternalType('array', $errorDetails);
        $this->assertEquals('someField', $errorDetails[0]['attribute']);
        $this->assertEquals('someErrorType', $errorDetails[0]['type']);
    }

    public function testUniqueConflict()
    {
        $error = new Error\UniqueConflict();
        $this->assertInstanceOf(Error\UniqueConflict::class, $error);
        $this->assertEquals(400, $error->getCode());
        $this->assertEquals('Unique Conflict', $error->getMessage());

        $error = new Error\UniqueConflict('Invalid Input data', 400, [
            'data' => [
                'message' => 'Custom error message',
                'details' => [
                    'errors' => [
                        [
                            'attribute' => 'values.email.value',
                            'existingID' => '123',
                            'completed' => 2
                        ]
                    ]
                ]
            ]
        ]);
        $this->assertInstanceOf(Error\UniqueConflict::class, $error);
        $this->assertEquals(400, $error->getCode());
        $this->assertEquals('Custom error message', $error->getMessage());
        $errorDetails = $error->getDetails();
        $this->assertInternalType('array', $errorDetails);
        $this->assertEquals('values.email.value', $errorDetails[0]['attribute']);
        $this->assertEquals('123', $errorDetails[0]['existingID']);
    }
}
