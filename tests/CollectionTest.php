<?php
namespace Akkroo\Tests;

use PHPUnit\Framework\TestCase;

use Akkroo\Collection;
use Akkroo\Resource;
use Akkroo\Company;

class CollectionTest extends TestCase
{
    /**
     * Test default and custom item types
     */
    public function testCustomItemType()
    {
        $c = new Collection([['foo' => 'bar'], ['foo' => 'baz']]);
        $this->assertInstanceOf(Collection::class, $c);
        $this->assertEquals(2, count($c));
        $this->assertInstanceOf(Resource::class, $c[0]);

        $c = new Collection([['foo' => 'bar'], ['foo' => 'baz']], Company::class);
        $this->assertInstanceOf(Company::class, $c[0]);
    }

    /**
     * Test that an exception is thrown if params is not array of arrays
     *
     * @expectedException \InvalidArgumentException
     */
    public function testArrayInputParams()
    {
        $c = new Collection(['foo', 'bar']);
    }

    /**
     * Test that no new items can be added after initialisation
     *
     * @expectedException \LogicException
     */
    public function testReadOnlyOnAdd()
    {
        $c = new Collection([['foo' => 'bar'], ['foo' => 'baz']]);
        $c[] = new Resource(['foo' => 123]);
    }

    /**
     * Test that collection items cannot be removed
     *
     * @expectedException \LogicException
     */
    public function testReadOnlyOnDelete()
    {
        $c = new Collection([['foo' => 'bar'], ['foo' => 'baz']]);
        unset($c[0]);
    }
}
