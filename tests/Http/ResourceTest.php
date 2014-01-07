<?php
namespace Http;

class ResourceTest extends \PHPUnit_Framework_TestCase
{
    public function testMatch()
    {
        Resource::$path = '/';
        $this->assertSame(array(), Resource::match('/foo'));
        $this->assertSame(array('/'), Resource::match('/'));

        Resource::$path = '/products/:name';
        $this->assertSame(array(), Resource::match('/about'));
        $this->assertSame(array(), Resource::match('/products/'));
        $expected = array('/products/a', 'name' => 'a', 'a');
        $this->assertSame($expected, Resource::match('/products/a'));

        Resource::$path = '/:foo/:bar/:baz';
        $expected = array('/one/two/three', 'foo' => 'one', 'one', 'bar' => 'two', 'two', 'baz' => 'three', 'three');
        $this->assertSame($expected, Resource::match('/one/two/three'));
    }

    public function testPath()
    {
        Resource::$path = '/';
        $this->assertSame('/', Resource::path());

        Resource::$path = '/:foo/:bar/:baz';
        $this->assertSame('/a/b/c', Resource::path('a', 'b', 'c'));
        $this->assertSame('/a', Resource::path('a'));
            # should propably error instead, missing 2 parameters

        Resource::$path = '/:foo';
        $this->assertSame('/wat', Resource::path('wat'));
    }
}
