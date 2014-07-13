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
        ResourceStub::$base = '';
        ResourceStub::$viewsDir = 'tests/views';
        ResourceStub::$viewsVars = array();
        ResourceStub::$layout = true;
        ResourceStub::$result = array('name' => 'Foo');
    }

    public function testMatch()
    {
        Resource::$path = '/';
        $this->assertSame(false, Resource::match('/foo'));
        $this->assertSame(array(), Resource::match('/'));

        Resource::$path = '/products/:name';
        $this->assertSame(false, Resource::match('/about'));
        $this->assertSame(false, Resource::match('/products/'));
        $this->assertSame(array('name' => 'a'), Resource::match('/products/a'));

        Resource::$path = '/a-b/:name';
        $this->assertSame(array('name' => 'a'), Resource::match('/a-b/a'));

        Resource::$path = '/a/*';
        $this->assertSame(array('rest' => 'foo/bar'), Resource::match('/a/foo/bar'));
        $this->assertSame(array('rest' => ''), Resource::match('/a/'));
        $this->assertSame(false, Resource::match('/a'));
        Resource::$path = '/a(/*)';
        $this->assertSame(array(), Resource::match('/a'));

        Resource::$path = '/:foo/:bar/:baz';
        $this->assertSame(array('foo' => 'one', 'bar' => 'two', 'baz' => 'three'), Resource::match('/one/two/three'));

        Resource::$path = '/:controller(/:action(/:id))';
        $this->assertSame(array('controller' => 'one'), Resource::match('/one'));
        $this->assertSame(array('controller' => 'one', 'action' => 'two'), Resource::match('/one/two'));
        $this->assertSame(array('controller' => 'one', 'action' => 'two', 'id' => '3'), Resource::match('/one/two/3'));
        $this->assertSame(false, Resource::match('/one/two/3/4'));

        Resource::$path = '/:file(.:format)';
        $this->assertSame(array('file' => 'one'), Resource::match('/one'));
        $this->assertSame(array('file' => 'one', 'format' => 'xml'), Resource::match('/one.xml'));

        Resource::$path = '/:controller(/:action(/:id(.:format)))';
        $this->assertSame(array('controller' => 'one'), Resource::match('/one'));
        $this->assertSame(array('controller' => 'one', 'action' => 'two', 'id' => '3', 'format' => 'xml'), Resource::match('/one/two/3.xml'));

        Resource::$path = '/:name.:extension';
        $this->assertSame(array('name' => 'a', 'extension' => 'png'), Resource::match('/a.png'));
    }

    public function testMatchWithBase()
    {
        ResourceStub::$base = '/foo';
        ResourceStub::$path = '/(:name)';
        $this->assertSame(array(), ResourceStub::match('/foo'));
    }

    public function testLink()
    {
        Resource::$path = '/';
        $this->assertSame('/', Resource::link());

        Resource::$path = '/products/:name';
        $this->assertSame('/products/yoyo', Resource::link('yoyo'));

        Resource::$path = '/a/*';
        $this->assertSame('/a/test', Resource::link('test'));

        Resource::$path = '/a(/*)';
        $this->assertSame('/a/test', Resource::link('test'));
        $this->assertSame('/a', Resource::link());

        Resource::$path = '/:foo/:bar/:baz';
        $this->assertSame('/a/b/c', Resource::link('a', 'b', 'c'));

        Resource::$path = '/:controller(/:action(/:id))';
        $this->assertSame('/one', Resource::link('one'));
        $this->assertSame('/one/two', Resource::link('one', 'two'));
        $this->assertSame('/one/two/12', Resource::link('one', 'two', 12));

        Resource::$path = '/:file(.:format)';
        $this->assertSame('/one.xml', Resource::link('one', 'xml'));

        Resource::$base = '/foo';
        Resource::$path = '/something';
        $this->assertSame('/foo/something', Resource::link());
    }

    /**
     * @expectedException \LogicException
     */
    public function testTooManyParams()
    {
        Resource::$path = '/:name/:id';
        Resource::link('test', 12, 'bar', 'giz');
    }

    /**
     * @expectedException \LogicException
     */
    public function testLinkMissingNameParam()
    {
        Resource::$path = '/:name/:id';
        Resource::link('test');
    }

    /**
     * @expectedException \LogicException
     */
    public function testLinkMissingRestParam()
    {
        Resource::$path = '/:name/*';
        Resource::link('test');
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
        $_SERVER['REQUEST_URI'] = '/page/not/found';
        $this->assertSame('<p>Oups Not Found</p>', $this->handleResourceStub());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleWithParams()
    {
        $_SERVER['REQUEST_URI'] = '/bar';
        $this->assertSame("<p>bar!\n</p>", $this->handleResourceStub());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleMethodNotAllowed()
    {
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $this->assertSame('<p>Oups Method Not Allowed</p>', $this->handleResourceStub());
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
    public function testHandleViewsVars()
    {
        ResourceStub::$viewsVars = array('foobar' => 'foobar');
        $this->assertSame("<p>Foo!\n</p>foobar", $this->handleResourceStub());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleWithoutLayout()
    {
        ResourceStub::$layout = false;
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
        ResourceStub::$result['lastModified'] = 1;
        $this->assertSame('', $this->handleResourceStub());
    }

    /**
     * @runInSeparateProcess
     */
    public function testHandleNotModifiedStdClass()
    {
        $_SERVER['HTTP_IF_MODIFIED_SINCE'] = 'Thu, 01 Jan 1970 00:00:01 +0000';
        ResourceStub::$result = new \stdClass();
        ResourceStub::$result->name = 'Foo';
        ResourceStub::$result->lastModified = 'Thu, 01 Jan 1970 00:00:01 +0000';
        $this->assertSame('', $this->handleResourceStub());
    }

    public function make($className)
    {
        return new $className();
    }

    private function handleResourceStub($factory = null, $classes = array('Http\ResourceStub'))
    {
        ob_start();
        Resource::handle($classes, $factory);
        return trim(ob_get_clean());
    }
}
