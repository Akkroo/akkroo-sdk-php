<?php
namespace Akkroo;

use ArrayObject;

class Collection extends ArrayObject
{
    protected $itemType = null;

    public function __construct($data = [], $itemType = Resource::class)
    {
        $this->itemType = $itemType;
        array_map(function ($item) use ($itemType) {
            $this->append(new $this->itemType($item));
        }, $data);
    }
}
