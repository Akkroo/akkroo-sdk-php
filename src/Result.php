<?php
namespace Akkroo;

use LogicException;
use JsonSerializable;

class Result implements JsonSerializable
{
    protected $data = [];
    protected $requestID = null;

    public function __construct(array $data = [])
    {
        if (isset($data['requestID'])) {
            $this->requestID = $data['requestID'];
            unset($data['requestID']);
        }
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

    public function __isset($name)
    {
        if ('requestID' === $name) {
            return isset($this->requestID);
        }
        return array_key_exists($name, $this->data);
    }

    public function __set($name, $value)
    {
        throw new LogicException('Cannot add properties to a read-only result');
    }

    public function __unset($name)
    {
        throw new LogicException('Cannot remove properties from a read-only result');
    }

    public function jsonSerialize()
    {
        return array_merge($this->data, ['requestID' => $this->requestID]);
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
