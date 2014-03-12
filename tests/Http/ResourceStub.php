<?php
namespace Http;

class ResourceStub extends Resource
{
    public static $path = '/';

    public function get()
    {
        return 'Foo';
    }

    public static function render($resource, $data)
    {
        echo $data . '!';
    }
}
