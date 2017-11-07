<?php
namespace Akkroo;

use ArrayObject;
use InvalidArgumentException;
use LogicException;
use JsonSerializable;

/**
 * Manage a collection of Akkroo resources
 *
 * The collection behaves like an array of objects, it can be filtered and serialized to JSON
 */
class Collection extends ArrayObject implements JsonSerializable
{
    protected $itemType = null;

    protected $writeable = true;

    protected $requestID = null;

    protected $meta = [];

    /**
     * Constructor
     *
     * @param   array  $data      Array of resources
     * @param   [type] $itemType  Acceptable resource class
     * @return  void
     */
    public function __construct(array $data = [], $itemType = Resource::class)
    {
        $this->itemType = $itemType;
        array_map(function ($item) use ($itemType) {
            if (!empty($item)) {
                if (is_array($item)) {
                    $this->append(new $this->itemType($item));
                    return $item;
                }
                if (is_a($item, $this->itemType)) {
                    $this->append($item);
                    return $item;
                }
            }
            throw new InvalidArgumentException('Invalid item data: array expected');
        }, $data);
        $this->writeable = false;
    }

    /**
     * Sets the value at the specified index to newval
     *
     * Wraps parent method to account for read-only collections
     *
     * @param  mixed $index  The index being set
     * @param  mixed $newval The new value for the index
     * @return void
     */
    public function offsetSet($index, $newval)
    {
        if ($this->writeable) {
            return parent::offsetSet($index, $newval);
        }
        throw new LogicException('Cannot add elements to a read-only collection');
    }

    /**
     * Unsets the value at the specified index
     *
     * Wraps parent method to account for read-only collections
     * Unlike offsetSet we don't need to check for witeable here,
     * because every collection is read-only once created and
     * cannot be made writeable
     *
     * @param  mixed $index The index being unset
     * @return void
     */
    public function offsetUnset($index)
    {
        throw new LogicException('Cannot remove elements from a read-only collection');
    }

    /**
     * Link a collection to an API request
     *
     * @param  string  $value  ID provided by HTTP client
     * @return \Akkroo\Collection
     */
    public function withRequestID($value)
    {
        $this->requestID = $value;
        return $this;
    }

    /**
     * Embed pagination metadata
     *
     * @param  array  $data Associative array of metadata
     * @return \Akkroo\Collection
     */
    public function withMeta(array $data)
    {
        $this->meta = $data;
        return $this;
    }

    /**
     * Fetch pagination metadata
     *
     * @param  string  $key  Specific key to retrieve
     * @return string|array
     */
    public function getMeta($key = null)
    {
        if (!empty($key)) {
            if (isset($this->meta[$key])) {
                return $this->meta[$key];
            }
            return null;
        }
        return $this->meta;
    }

    /**
     * Customize JSON encode output
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->getArrayCopy();
    }

    /**
     * Filters elements of an array using a callback function
     *
     * @param  callable $callback  The callback function to use
     * @return \Akkroo\Collection  The filtered collection
     */
    public function filter($callback)
    {
        return new self(array_filter($this->getArrayCopy(), $callback), $this->itemType);
    }
}
