<?php
namespace Http;

class ResourceStub extends Resource
{
    public static $path = '/(:name)';
    public static $errors = array();
    public static $onError = array('\Http\ResourceStub', 'onError');
    public static $result = array();
    public $name;
    public $data;

    public function get()
    {
        if ($this->name) {
            static::$result['name'] = $this->name;
        }
        if (isset(static::$result['lastModified'])) {
            $this->lastModified = static::$result['lastModified'];
        }
        $this->data = static::$result;
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
