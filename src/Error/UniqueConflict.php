<?php
namespace Akkroo\Error;

class UniqueConflict extends Generic
{
    protected $message = 'Unique Conflict';
    protected $details = [];

    public function __construct($message = '', $code = 400, array $body = [])
    {
        parent::__construct($message, $code, $body);
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
