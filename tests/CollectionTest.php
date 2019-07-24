<?php
namespace Akkroo\Tests;

use PHPUnit\Framework\TestCase;

use Akkroo\Collection;
use Akkroo\Resource;
use Akkroo\Record;

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

        $c = new Collection([['foo' => 'bar'], ['foo' => 'baz']], Record::class);
        $this->assertInstanceOf(Record::class, $c[0]);
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

    /**
     * Test that our custom JSON is exported when a collection is serialized
     */
    public function testJSONSerialize()
    {
        $c = new Collection([['foo' => 'bar'], ['foo' => 'baz']]);
        $json = json_encode($c);
        $this->assertEquals('[{"foo":"bar","requestID":null},{"foo":"baz","requestID":null}]', $json);
    }

    /**
     * Test that a collection can be filtered
     */
    public function testFilter()
    {
        $c = new Collection([['foo' => 'bar'], ['foo' => 'baz']]);
        $f = $c->filter(function ($item) {
            return $item->foo !== 'baz';
        });
        $this->assertInstanceOf(Collection::class, $f);
        $this->assertEquals(1, count($f));
        $this->assertEquals('bar', $f[0]->foo);
    }
}
