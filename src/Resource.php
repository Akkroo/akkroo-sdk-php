<?php
namespace Akkroo;

use InvalidArgumentException;

class Resource extends Result
{
    /**
     * Create a resource object
     * @param  string $resourceName Name of the resource (i.e. events, records)
     * @param  array  $data         Recource data
     * @param  array  $params       Resource parameters
     * @param  array  $meta         Resource metadata
     * @return Event|Record
     */
    public static function create($resourceName, $data, $params = [], $meta = [])
    {
        $createCollection = isset($data[0]);
        switch ($resourceName) {
            case 'events':
                $resourceClass = Event::class;
                $createCollection = empty($params['id']) && empty($data['id']);
                break;
            case 'records':
                $resourceClass = Record::class;
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

    public function __set(string $name, $value)
    {
        $this->data[$name] = $value;
    }

    public function __unset(string $name)
    {
        unset($this->data[$name]);
    }
}
