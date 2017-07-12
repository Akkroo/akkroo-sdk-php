<?php
namespace Akkroo\Tests;

use PHPUnit\Framework\TestCase;

use Akkroo\Result;

class ResultTest extends TestCase
{
    public function testThatAResultIsCreated()
    {
        $result = new Result(['success' => true, 'foo' => 'bar']);
        $this->assertInstanceOf(Result::class, $result);
        $this->assertTrue($result->success);
        $this->assertEquals('bar', $result->foo);
        $this->assertNull($result->unknownProperty);
    }

    public function testResultWithRequestID()
    {
        $result = (new Result(['success' => true]))->withRequestID('CustomRequestID');
        $this->assertEquals('CustomRequestID', $result->requestID);
    }

    /**
     * @expectedException \LogicException
     */
    public function testReadOnlyResult()
    {
        $result = new Result(['success' => true, 'foo' => 'bar']);
        $result->foo = 'other';
    }

    public function testResultToArray()
    {
        $result = new Result(['success' => true, 'foo' => 'bar']);
        $this->assertInstanceOf(Result::class, $result);
        $arrayResult = $result->toArray();
        $this->assertInternalType('array', $arrayResult);
        $this->assertTrue($arrayResult['success']);
    }
}
