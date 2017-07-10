<?php
namespace Akkroo\Error;

use Exception;

class Generic extends Exception
{
    public function __construct($message = 'Bad Request', $code = 400, array $body = [])
    {
        parent::__construct($message, $code);
        if (!empty($body['error'])) {
            $this->message .= ' (' . $body['error'] . ')';
        }
        if (!empty($body['error_description'])) {
            $this->message .= ': ' . $body['error_description'];
        }
    }
}
