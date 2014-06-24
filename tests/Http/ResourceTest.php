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
        unset($_SERVER['HTTP_IF_MODIFIED_SINCE']);
        \Http\ResourceStub::$viewsDir = 'tests/views';
        \Http\ResourceStub::$layout = true;
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
        Resource::$path = '/';
        $this->assertSame('/', Resource::link(array()));

        Resource::$path = '/products/:name';
        $this->assertSame('/products/yoyo', Resource::link(array('name' => 'yoyo')));

        Resource::$path = '/a/*';
        $this->assertSame('/a/test', Resource::link(array('*' => 'test')));

        Resource::$path = '/a(/*)';
        $this->assertSame('/a/test', Resource::link(array('*' => 'test')));
        $this->assertSame('/a', Resource::link(array()));

        Resource::$path = '/:foo/:bar/:baz';
        $this->assertSame('/a/b/c', Resource::link(array('foo' => 'a', 'bar' => 'b', 'baz' => 'c')));

        Resource::$path = '/:controller(/:action(/:id))';
        $this->assertSame('/one', Resource::link(array('controller' => 'one')));
        $this->assertSame('/one/two', Resource::link(array('controller' => 'one', 'action' => 'two')));
        $this->assertSame('/one/two/12', Resource::link(array('controller' => 'one', 'action' => 'two', 'id' => 12)));

        Resource::$path = '/:file(.:format)';
        $this->assertSame('/one.xml', Resource::link(array('file' => 'one', 'format' => 'xml')));

        Resource::$base = '/foo';
        Resource::$path = '/something';
        $this->assertSame('/foo/something', Resource::link());
    }

    /**
     * @expectedException \LogicException
     */
    public function testLinkMissingNameParam()
    {
        Resource::$path = '/:name/:id';
        Resource::link(array('name' => 'test'));
    }

    /**
     * @expectedException \LogicException
     */
    public function testLinkMissingRestParam()
    {
        Resource::$path = '/:name/*';
        Resource::link(array('name' => 'test'));
    }

    /**
     * @expectedException \Http\MethodNotAllowed
     */
    public function testHead()
    {
        $resource = new Resource();
        $resource->head();
    }

    public function testDefaultMethods()
    {
        $this->assertTrue(method_exists('Http\Resource', 'head'));
        $this->assertTrue(method_exists('Http\Resource', 'get'));
        $this->assertFalse(method_exists('Http\Resource', 'post'));
        $this->assertFalse(method_exists('Http\Resource', 'put'));
        $this->assertFalse(method_exists('Http\Resource', 'delete'));
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
        $this->assertSame('<p>Oups Not Found</p>', $this->handleResourceStub());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleInternalServerError()
    {
        $_SERVER['REQUEST_METHOD'] = 'DELETE'; // will trigger an exception on the stub
        $this->assertEmpty(ResourceStub::$errors);
        $this->assertSame('<p>Oups Internal Server Error</p>', $this->handleResourceStub());
        $this->assertNotEmpty(ResourceStub::$errors, 'onError should be called');
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleSimple()
    {
        $this->assertSame("<p>Foo!\n</p>", $this->handleResourceStub());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleWithoutLayout()
    {
        Resource::$layout = false;
        $this->assertSame('Foo!', $this->handleResourceStub());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandlerFactory()
    {
        $this->assertSame("<p>Foo!\n</p>", $this->handleResourceStub(array($this, 'make')));
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleNotModified()
    {
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = 'Thu, 01 Jan 1970 00:00:01 +0000';
        $this->assertSame('', $this->handleResourceStub());
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
