<?php
namespace Akkroo\Error;

use Exception;

class Generic extends Exception
{
    protected $code = 400;
    protected $message = 'Bad Request';
    protected $requestID = null;

    public function __construct($message = '', $code = 0, array $body = [])
    {
        if (empty($message)) {
            $message = $this->message;
        }
        if (empty($code)) {
            $code = $this->code;
        }
        parent::__construct($message, $code);
        if (!empty($body['data']['error'])) {
            $message = isset($body['data']['error']['message'])
                ? $body['data']['error']['message']
                : $body['data']['error'];
            $this->message .= ' (' . $message . ')';
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
