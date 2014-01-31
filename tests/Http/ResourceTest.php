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
        $expected = array('/products/a', 'name' => 'a');
        $this->assertSame($expected, Resource::match('/products/a'));

        Resource::$path = '/:foo/:bar/:baz';
        $expected = array('/one/two/three', 'foo' => 'one', 'bar' => 'two', 'baz' => 'three');
        $this->assertSame($expected, Resource::match('/one/two/three'));

        Resource::$path = '/:controller(/:action(/:id))';
        $this->assertSame(array('/one', 'controller' => 'one'), Resource::match('/one'));
        $this->assertSame(array('/one/two', 'controller' => 'one', 'action' => 'two'), Resource::match('/one/two'));
        $this->assertSame(array('/one/two/3', 'controller' => 'one', 'action' => 'two', 'id' => '3'), Resource::match('/one/two/3'));
        $this->assertSame(array(), Resource::match('/one/two/3/4'));

        Resource::$path = '/:file(.:format)';
        $this->assertSame(array('/one', 'file' => 'one'), Resource::match('/one'));
        $this->assertSame(array('/one.xml', 'file' => 'one', 'format' => 'xml'), Resource::match('/one.xml'));

        Resource::$path = '/:controller(/:action(/:id(.:format)))';
        $this->assertSame(array('/one', 'controller' => 'one'), Resource::match('/one'));
        $this->assertSame(array('/one/two/3.xml', 'controller' => 'one', 'action' => 'two', 'id' => '3', 'format' => 'xml'), Resource::match('/one/two/3.xml'));
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
