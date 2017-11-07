<?php
namespace Akkroo;

use ArrayObject;
use InvalidArgumentException;
use LogicException;
use JsonSerializable;

class Collection extends ArrayObject implements JsonSerializable
{
    protected $itemType = null;

    protected $writeable = true;

    protected $requestID = null;

    protected $meta = [];

    public function __construct(array $data = [], $itemType = Resource::class)
    {
        $this->itemType = $itemType;
        array_map(function ($item) use ($itemType) {
            if (!empty($item) && is_array($item)) {
                $this->append(new $this->itemType($item));
                return $item;
            }
            throw new InvalidArgumentException('Invalid item data: array expected');
        }, $data);
        $this->writeable = false;
    }

    public function offsetSet($index, $newval)
    {
        if ($this->writeable) {
            return parent::offsetSet($index, $newval);
        }
        throw new LogicException('Cannot add elements to a read-only collection');
    }

    public function offsetUnset($index)
    {
        throw new LogicException('Cannot remove elements from a read-only collection');
    }

    public function withRequestID($value)
    {
        $this->requestID = $value;
        return $this;
    }

    public function withMeta(array $data)
    {
        $this->meta = $data;
        return $this;
    }

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

    public function jsonSerialize()
    {
        return (array) $this;
    }
}
