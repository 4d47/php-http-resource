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
        Resource::$viewsDir = 'tests/views';
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

    public function testDefaultMethods()
    {
        $resource = new Resource();
        $resource->head();
        $resource->get();
        $this->assertFalse(method_exists('Http\Resource', 'post'));
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleRedirectTrailingSlash()
    {
        if (!extension_loaded('xdebug')) {
            $this->markTestSkipped('Requires xdebug to test headers');
            return;
        }
        $_SERVER['REQUEST_URI'] = '/foo/';
        $this->handleResourceStub();
        $this->assertSame(array('Location: /foo'), xdebug_get_headers());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleNotFound()
    {
        $_SERVER['REQUEST_URI'] = '/not-found';
        $this->assertSame('Oups Not Found', $this->handleResourceStub());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleInternalServerError()
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE'; // will trigger an exception on the stub
        $this->assertEmpty(ResourceStub::$errors);
        $this->assertSame('Oups Internal Server Error', $this->handleResourceStub());
        $this->assertNotEmpty(ResourceStub::$errors, 'onError should be called');
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandle()
    {
        $this->assertSame('Foo!', $this->handleResourceStub());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandlerFactory()
    {
        $this->assertSame('Foo!', $this->handleResourceStub(array($this, 'make')));
    }

    public function make($className)
    {
        return new $className();
    }

    private function handleResourceStub($factory = null)
    {
        ob_start();
        Resource::handle(array('Http\ResourceStub'), $factory);
        return trim(ob_get_clean());
    }
}
