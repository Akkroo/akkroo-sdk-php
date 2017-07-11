<?php
namespace Akkroo;

use ArrayObject;
use InvalidArgumentException;
use LogicException;

class Collection extends ArrayObject
{
    protected $itemType = null;

    protected $writeable = true;

    public function __construct(array $data = [], $itemType = Resource::class)
    {
        $this->itemType = $itemType;
        array_map(function ($item) use ($itemType) {
            if (!is_array($item)) {
                throw new InvalidArgumentException('Invalid item data: array expected');
            }
            $this->append(new $this->itemType($item));
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
}
