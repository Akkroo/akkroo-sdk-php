<?php
namespace Akkroo\Tests;

use PHPUnit\Framework\TestCase;

use Akkroo\Resource;
use Akkroo\Event;
use Akkroo\Collection;

class ResourceTest extends TestCase
{
    /**
     * Test that a known resource type is created without errors
     */
    public function testKnownResourceType()
    {
        $event = Resource::create('events', ['id' => 123, 'name' => 'Some Event']);
        $this->assertInstanceOf(Event::class, $event);
        $this->assertNull($event->unknownProperty);
    }

    /**
     * Test that a unknown resource type throws an error
     * @expectedException \InvalidArgumentException
     */
    public function testErrorOnUnknownResourceType()
    {
        $event = Resource::create('foo', []);
    }

    /**
     * Test that a collection of resources is created
     */
    public function testCollectionResource()
    {
        $events = Resource::create('events', [
            ['name' => 'Some Event'],
            ['name' => 'Other Event']
        ]);
        $this->assertInstanceOf(Collection::class, $events);
    }

    public function testEditableResource()
    {
        $event = Resource::create('events', ['id' => 123, 'name' => 'Some Event']);
        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals(123, $event->id);
        $this->assertEquals('Some Event', $event->name);
        $event->name = 'Another Event';
        $this->assertEquals('Another Event', $event->name);
        $this->assertObjectNotHasAttribute('name', $event);
        unset($event->name);
        $this->assertFalse(isset($event->name));
    }
}
