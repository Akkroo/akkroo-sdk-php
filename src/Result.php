<?php
namespace Akkroo;

use LogicException;
use JsonSerializable;

/**
 * A basic response result object
 *
 * @property string access_token    Authentication token for successful login results
 * @property int    expires_in      Authentication token duration for successful login results
 * @property string refresh_token   Refresh token for successful login results
 * @property-read string requestID  Identifier for an HTTP request
 */
class Result implements JsonSerializable
{
    /**
     * Result data payload
     * @var array
     */
    protected $data = [];

    /**
     * @var string|null
     */
    protected $requestID = null;

    public function __construct(array $data = [])
    {
        if (isset($data['requestID'])) {
            $this->requestID = $data['requestID'];
            unset($data['requestID']);
        }
        $this->data = $data;
    }

    public function __get(string $name)
    {
        if ('requestID' === $name) {
            return $this->requestID;
        }
        if (array_key_exists($name, $this->data)) {
            return $this->data[$name];
        }
        return null;
    }

    public function __isset(string $name)
    {
        if ('requestID' === $name) {
            return isset($this->requestID);
        }
        return array_key_exists($name, $this->data);
    }

    public function __set(string $name, $value)
    {
        throw new LogicException('Cannot add properties to a read-only result');
    }

    public function __unset(string $name)
    {
        throw new LogicException('Cannot remove properties from a read-only result');
    }

    /**
     * Customize JSON encode output
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    /**
     * Link a result to an API request
     *
     * @param string $value A request ID to associate
     * @return Result
     */
    public function withRequestID(string $value)
    {
        $this->requestID = $value;
        return $this;
    }

    /**
     * Export internal data as array
     *
     * @return array
     */
    public function toArray()
    {
        return array_merge($this->data, ['requestID' => $this->requestID]);
    }
}
