<?php
namespace Http;

class ResourceStub extends Resource
{
    public static $path = '/';
    public static $errors = array();
    public static $onError = array('\Http\ResourceStub', 'onError');
    public static $result = array();

    public function get()
    {
        return static::$result;
    }

    public function delete()
    {
        throw new \Exception('foo');
    }

    public static function onError($error)
    {
        self::$errors[] = $error;
    }
}
