<?php
namespace Akkroo\Tests;

use PHPUnit\Framework\TestCase;

use Akkroo\Resource;
use Akkroo\Company;
use Akkroo\Event;
use Akkroo\Collection;

class ResourceTest extends TestCase
{
    /**
     * Test that a known resource type is created without errors
     */
    public function testKnownResourceType()
    {
        $company = Resource::create('company', ['name' => 'Some Company']);
        $this->assertInstanceOf(Company::class, $company);
        $this->assertNull($company->unknownProperty);
    }

    /**
     * Test that a unknown resource type throws an error
     * @expectedException \InvalidArgumentException
     */
    public function testErrorOnUnknownResourceType()
    {
        $company = Resource::create('foo', []);
    }

    /**
     * Test that a collection of resources is created
     */
    public function testCollectionResource()
    {
        $companies = Resource::create('company', [
            ['name' => 'Some Company'],
            ['name' => 'Other Company']
        ]);
        $this->assertInstanceOf(Collection::class, $companies);
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
    }
}
