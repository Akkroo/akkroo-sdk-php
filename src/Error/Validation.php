<?php
namespace Akkroo\Error;

class Validation extends Generic
{
    protected $details = [];

    public function __construct($message = 'Validation Error', $code = 400, array $body = [])
    {
        parent::__construct($message, $code, $body);
        $this->message = $message;
        if (!empty($body['data']['message'])) {
            $this->message = $body['data']['message'];
        }
        if (!empty($body['data']['details']['errors'])) {
            $this->details = $body['data']['details']['errors'];
        }
    }

    public function getDetails()
    {
        return $this->details;
    }
}
