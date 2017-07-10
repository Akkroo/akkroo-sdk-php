<?php
namespace Akkroo\Error;

use Exception;

class Generic extends Exception
{
    protected $requestID = null;

    public function __construct($message = 'Bad Request', $code = 400, array $body = [])
    {
        parent::__construct($message, $code);
        if (!empty($body['error'])) {
            $this->message .= ' (' . $body['error'] . ')';
        }
        if (!empty($body['error_description'])) {
            $this->message .= ': ' . $body['error_description'];
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
