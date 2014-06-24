<?php
namespace Http;

class ResourceStub extends Resource
{
    public static $path = '/';
    public static $errors = array();
    public static $onError = array('\Http\ResourceStub', 'onError');

    public function get()
    {
        return array('name' => 'Foo', 'lastModified' => 1);
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
