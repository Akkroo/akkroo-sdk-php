<?php
namespace Akkroo\Error;

class NotFound extends Generic
{
    protected $code = 404;
    protected $message = 'Resource Not Found';
}
