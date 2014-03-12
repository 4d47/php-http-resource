<?php
namespace Http;

class ResourceTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.0';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SERVER_NAME'] = 'example.com';
        $_SERVER['SERVER_PORT'] = '80';
        $_SERVER['REQUEST_URI'] = '/';
    }

    public function testMatch()
    {
        Resource::$path = '/';
        $this->assertSame(array(), Resource::match('/foo'));
        $this->assertSame(array('/'), Resource::match('/'));

        Resource::$path = '/products/:name';
        $this->assertSame(array(), Resource::match('/about'));
        $this->assertSame(array(), Resource::match('/products/'));
        $this->assertSame(array('/products/a', 'name' => 'a'), Resource::match('/products/a'));

        Resource::$path = '/a-b/:name';
        $this->assertSame(array('/a-b/a', 'name' => 'a'), Resource::match('/a-b/a'));

        Resource::$path = '/a/*';
        $this->assertSame(array('/a/foo/bar', 'rest' => 'foo/bar'), Resource::match('/a/foo/bar'));
        $this->assertSame(array('/a/', 'rest' => ''), Resource::match('/a/'));
        $this->assertSame(array(), Resource::match('/a'));
        Resource::$path = '/a(/*)';
        $this->assertSame(array('/a'), Resource::match('/a'));

        Resource::$path = '/:foo/:bar/:baz';
        $this->assertSame(array('/one/two/three', 'foo' => 'one', 'bar' => 'two', 'baz' => 'three'), Resource::match('/one/two/three'));

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

        Resource::$path = '/:name.:extension';
        $this->assertSame(array('/a.png', 'name' => 'a', 'extension' => 'png'), Resource::match('/a.png'));
    }

    public function testLink()
    {
        $this->assertSame('http://example.com/', Resource::link());
        Resource::$base[] = 'admin';
        $this->assertSame('http://example.com/admin/a', Resource::link('a'));
    }

    public function testPath()
    {
        $this->assertSame('/', Resource::path());
        $this->assertSame('/a/b/c', Resource::path('a', 'b', 'c'));
        $this->assertSame('/a', Resource::path('a'));
        $_SERVER['REDIRECT_BASE'] = '/admin/';
        $this->assertSame('/admin', Resource::path());
        $this->assertSame('/admin/a', Resource::path('a'));
        Resource::$base[] = 'admin';
        $this->assertSame('/admin/a', Resource::path('a'));
    }

    public function testUrl()
    {
        $this->assertSame('http://example.com/a', Resource::url('a'));
        Resource::$base[] = 'admin';
        $this->assertSame('http://example.com/a', Resource::url('a'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandle()
    {
        ob_start();
        Resource::handle(array('Http\ResourceStub'));
        $result = ob_get_clean();
        $this->assertSame('Foo!', $result);
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandlerFactory()
    {
        ob_start();
        Resource::handle(array('Http\ResourceStub'), array($this, 'make'));
        $result = ob_get_clean();
        $this->assertSame('Foo!', $result);
    }

    public function make($className)
    {
        return new $className();
    }
}
