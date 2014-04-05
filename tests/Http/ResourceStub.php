<?php
namespace Http;

class ResourceStub extends Resource
{
    public static $path = '/';
    public static $viewsDir = 'tests/views';

    public function get()
    {
        return array('name' => 'Foo');
    }
}
