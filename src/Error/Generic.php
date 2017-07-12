<?php
namespace Akkroo\Error;

use Exception;

class Generic extends Exception
{
    protected $requestID = null;

    public function __construct($message = 'Bad Request', $code = 400, array $body = [])
    {
        parent::__construct($message, $code);
        if (!empty($body['data']['error'])) {
            $this->message .= ' (' . $body['data']['error'] . ')';
        }
        if (!empty($body['data']['error_description'])) {
            $this->message .= ': ' . $body['data']['error_description'];
        }
        if (!empty($body['requestID'])) {
            $this->requestID = $body['requestID'];
        }
    }

    public function getRequestID()
    {
        return $this->requestID;
    }
}
