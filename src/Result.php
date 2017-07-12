<?php
namespace Akkroo;

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

    public function withRequestID($value)
    {
        $this->requestID = $value;
        return $this;
    }
}
