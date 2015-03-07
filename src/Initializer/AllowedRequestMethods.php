<?php
namespace Http\Initializer;

class AllowedRequestMethods
{
    public static $methods = ['GET', 'POST', 'PUT', 'DELETE', 'HEAD'];

    public function __invoke()
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], static::$methods)) {
            throw new \Http\MethodNotAllowed();
        }
    }
}

