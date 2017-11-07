<?php
namespace Akkroo;

use InvalidArgumentException;

class Resource extends Result
{
    public static function create($resourceName, $data, $params = [], $meta = [])
    {
        $createCollection = isset($data[0]);
        switch ($resourceName) {
            case 'company':
                $resourceClass = Company::class;
                break;
            case 'events':
                $resourceClass = Event::class;
                $createCollection = empty($params['id']) && empty($data['id']);
                break;
            case 'registrations':
                $resourceClass = Registration::class;
                $createCollection = empty($params['id']) && empty($data['id']);
                break;
            default:
                throw new InvalidArgumentException(sprintf('Unknown resource "%s"', $resourceName));
        }
        if ($createCollection) {
            // We have a collection
            return (new Collection($data, $resourceClass))->withMeta($meta);
        }
        return new $resourceClass($data);
    }

    public function __set($name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __unset($name)
    {
        unset($this->data[$name]);
    }
}
