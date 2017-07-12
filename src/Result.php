<?php
namespace Akkroo;

use LogicException;

class Result
{
    protected $data = [];
    protected $requestID = null;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    public function __get($name)
    {
        if ('requestID' === $name) {
            return $this->requestID;
        }
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        return null;
    }

    public function __set($name, $value)
    {
        throw new LogicException('Cannot add properties to a read-only result');
    }

    /**
     * @param string $value A request ID to associate
     * @return Result
     */
    public function withRequestID($value)
    {
        $this->requestID = $value;
        return $this;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->data;
    }
}
