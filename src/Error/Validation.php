<?php
namespace Akkroo\Error;

class Validation extends Generic
{
    protected $message = 'Validation Error';
    protected $details = [];

    public function __construct($message = '', $code = 400, array $body = [])
    {
        parent::__construct($message, $code, $body);
        if (!empty($body['data']['error']['message'])) {
            $this->message = $body['data']['error']['message'];
        }
        if (!empty($body['data']['error']['data'])) {
            $this->details = $body['data']['error']['data'];
        }
    }

    public function getDetails()
    {
        return $this->details;
    }
}
