<?php
namespace Akkroo;

use InvalidArgumentException;

class Resource extends Result
{
    public static function create($resourceName, $data)
    {
        switch ($resourceName) {
            case 'company':
                $resourceClass = Company::class;
                break;
            default:
                throw new InvalidArgumentException(sprintf('Unknown resource "%s"', $resourceName));
        }
        if (isset($data[0])) {
            // We have a collection
            return new Collection($data, $resourceClass);
        }
        return new $resourceClass($data);
    }
}
