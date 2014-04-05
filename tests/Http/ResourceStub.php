<?php
namespace Http;

class ResourceStub extends Resource
{
    public static $path = '/';

    public function get()
    {
        return array('name' => 'Foo');
    }
}
