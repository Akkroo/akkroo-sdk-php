<?php
namespace Akkroo\Error;

class Authentication extends Generic
{
    protected $code = 401;
    protected $message = 'Unauthorized';
}
