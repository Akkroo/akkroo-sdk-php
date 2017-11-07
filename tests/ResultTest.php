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
        $this->assertTrue(isset($result->foo));
        $this->assertEquals('bar', $result->foo);
        $this->assertNull($result->unknownProperty);
    }

    public function testResultWithRequestID()
    {
        $result = (new Result(['success' => true]))->withRequestID('CustomRequestID');
        $this->assertTrue(isset($result->requestID));
        $this->assertEquals('CustomRequestID', $result->requestID);
    }

    /**
     * @expectedException \LogicException
     */
    public function testReadOnlyResultOnEdit()
    {
        $result = new Result(['success' => true, 'foo' => 'bar']);
        $result->foo = 'other';
    }

    /**
     * @expectedException \LogicException
     */
    public function testReadOnlyResultOnUnset()
    {
        $result = new Result(['success' => true, 'foo' => 'bar']);
        unset($result->foo);
    }

    public function testResultToArray()
    {
        $result = new Result(['success' => true, 'foo' => 'bar']);
        $this->assertInstanceOf(Result::class, $result);
        $arrayResult = $result->toArray();
        $this->assertInternalType('array', $arrayResult);
        $this->assertTrue($arrayResult['success']);
    }

    public function testJsonSerialize()
    {
        $result = new Result(['success' => true, 'foo' => 'bar']);
        $this->assertInstanceOf(Result::class, $result);
        $json = json_encode($result);
        $this->assertEquals('{"success":true,"foo":"bar","requestID":null}', $json);
    }
}
