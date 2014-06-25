<?php
namespace Http;

class ResourceStub extends Resource
{
    public static $path = '/(:name)';
    public static $errors = array();
    public static $onError = array('\Http\ResourceStub', 'onError');
    public static $result = array();
    public $name;

    public function get()
    {
        if ($this->name) {
            static::$result['name'] = $this->name;
        }
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
