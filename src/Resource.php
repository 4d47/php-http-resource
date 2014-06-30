<?php
namespace Http;

/**
 * Base class to represent an HTTP resource; 
 * acting as an adapter between HTTP and your business logic.
 *
 * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html
 */
class Resource
{
    /**
     * The Request-URI pattern this resource match.
     *
     *     eg. /products/:name(.:format)
     * 
     * Colon prefixed segments are parameters that
     * will be assigned as instance properties.
     * Optional parameters are denoted by parentheses.
     * 
     * @var string
     */
    public static $path = '/';

    /**
     * Base of $path.
     *
     * @var string
     */
    public static $base = '';

    /**
     * The attribute name containing the last modified
     * datetime in the in `get` response.
     *
     * @var string
     */
    public static $lastModifiedName = 'lastModified';

    /**
     * The base directory of views script.
     *
     * @var string
     */
    public static $viewsDir = 'views';

    /**
     * Callback to handle exceptions
     * Should not throw exception
     *
     * @var callback
     */
    public static $onError = 'error_log';

    /**
     * Should the layout be used in render.
     *
     * @var boolean
     */
    public static $layout = true;

    /**
     * Route to a matching resource, calling the appropriate
     * HTTP method and render() the response.
     *
     * @param array of \Http\Resource
     * @param callback \Http\Resource factory function, default to `new`
     * @return void
     */
    public static function handle(array $resources, $factory = null)
    {
        try {
            $factory = $factory ?: function ($className) { return new $className(); };
            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

            // safeguard, parse_url should work
            if ($uri === false) {
                throw new NotFound();
            }

            // begin opinionated here ...
            if (preg_match('|.+/$|', $uri)) {
                throw new MovedPermanently( rtrim($uri, '/') . (empty($_SERVER['QUERY_STRING']) ? "" : "?{$_SERVER['QUERY_STRING']}") );
            }

            // method override
            if (isset($_GET['_method'])) {
                $_SERVER['REQUEST_METHOD'] = strtoupper($_GET['_method']);
            }

            if (!in_array($_SERVER['REQUEST_METHOD'], array('GET', 'POST', 'PUT', 'DELETE', 'HEAD'))) {
                throw new MethodNotAllowed();
            }

            // lookup $resources and call appropriate method
            foreach ($resources as $className) {
                $params = $className::match($uri);
                if ($params !== false) {
                    $resource = call_user_func($factory, $className);
                    $response = $resource->dispatch($params);
                    $resource->render($response);
                    break;
                }
            }

            if (empty($resource)) {
                throw new NotFound();
            }

        } catch (NotModified $e) {
            // don't output anything
        } catch (Redirection $e) {
            header("Location: $e->location", true, $e->code);
            echo $e->getMessage();
        } catch (Exception $e) {
            // fallback to default view rendering
        } catch (\Exception $e) {
            $fn = isset($resource) ? $resource::$onError : static::$onError;
            call_user_func($fn, $e);
            $e = new InternalServerError($e->getMessage(), $e);
        }
        /* finally */ if (isset($e)) {
            header("{$_SERVER['SERVER_PROTOCOL']} $e->code $e->reason");
            if ($e instanceof \Http\Error) {
                // not rendering redirects
                static::renderResource($e, array('error' => $e));
            }
        }
    }

    /**
     * Initialize resource with $params and call appropiate method.
     *
     * @param array $params
     * @return mixed
     */
    public function dispatch(array $params)
    {
        if (!method_exists($this, $_SERVER['REQUEST_METHOD'])) {
            throw new MethodNotAllowed();
        }
        // assign params to resource and initialize
        foreach ($params as $key => $param) {
            $this->$key = $param;
        }
        $this->init();
        $response = $this->{ $_SERVER['REQUEST_METHOD'] }();
        $lastModified = $this::getLastModified($response);
        // caching headers
        if ($lastModified) {
            header("Last-Modified: $lastModified");
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $lastModified) {
                throw new NotModified(null);
            }
        }
        return $response;
    }

    /**
     * Test if the resource path match.
     *
     * @param string Request-URI to match against
     * @return false|array of URI pattern variables
     */
    public static function match($uri)
    {
        $replacements = array(
            '/\(/' => '(',
            '/\)/' => ')?',
            '/\./' => '\.',
            '/:(\w+)/' => '(?P<$1>[^./]+)',
            '/\*/' => '(?P<rest>.*?)',
        );
        $path = preg_replace('/^\//', '/?', static::$path);
        $route = static::$base . $path;
        foreach ($replacements as $pattern => $replacement) {
            $route = preg_replace($pattern, $replacement, $route);
        }
        preg_match("{^$route$}", $uri, $matches);
        if (empty($matches)) {
            return false;
        }
        foreach (array_keys($matches) as $key) {
            if (is_integer($key)) {
                unset($matches[$key]);
            }
        }
        return $matches;
    }

    /**
     * Substitutes $vars in the $path.
     *
     * @param array $vars
     */
    public static function link(array $vars = array())
    {
        $result = static::$base . static::$path;
        foreach ($vars as $name => $value) {
            $pattern = $name == '*' ? '/\*/' : "/:$name/";
            $result = preg_replace($pattern, $value, $result);
        }
        // OMG! that's the most horrific regexes I've seen !
        $result = preg_replace('/\([^(]*[:*].*\)/', '', $result);
        $result = preg_replace('/[()]/', '', $result);
        if (preg_match('/[:*]/', $result)) {
            throw new \LogicException("Incomplete link: $result");
        }
        return $result;
    }

    /**
     * Called after pattern variables are assigned.
     */
    public function init()
    {
        ;
    }

    /**
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.4
     */
    public function head()
    {
        return $this->get();
    }

    /**
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.3
     */
    public function get()
    {
        throw new \Http\MethodNotAllowed();
    }

    /**
     * Renders a $response using `renderResource` strategy.
     *
     * @param mixed data resulted from the resource method
     */
    public function render($response)
    {
        static::renderResource($this, $response);
    }

    /**
     * Render a $resource's $response data to the browser.
     * Provides a basic two step view implementation.
     *
     * @param \Http\Resource|\Http\Exception
     * @param mixed data resulted from the resource method
     */
    protected static function renderResource($resource, $response)
    {
        // first step, logical presentation
        $resourceClass = get_class($resource);
        $content = '';
        do {
            $name = static::classToPath($resourceClass);
            $file = static::$viewsDir . "/$name.php";
            if (file_exists($file)) {
                $content = static::partial($file, $response);
                break;
            }
            // try parent classes
        } while (false != ($resourceClass = get_parent_class($resourceClass)));

        // second step, layout formatting
        if (static::$layout) {
            $name = static::classToPath(get_class($resource));
            do {
                $name = dirname($name);
                $file = static::$viewsDir . "/$name/layout.php";
                if (file_exists($file)) {
                    $content = static::partial($file, array('content' => $content));
                    break;
                }
            } while ($name != '.');
        }
        echo $content;
    }

    /**
     * Require a file, controlling it's symbol table.
     *
     * @param string Filename
     * @param array|object Variables to be extracted
     * @return string
     */
    protected static function partial()
    {
        ob_start();
        extract($GLOBALS);
        extract((array) func_get_arg(1));
        require func_get_arg(0);
        return ob_get_clean();
    }

    /**
     * Convert My\FooBar to my/foo_bar
     *
     * @param string
     * @return string
     */
    protected static function classToPath($className)
    {
        return str_replace('\\', '/', strtolower(preg_replace('/(?<=\\w)([A-Z])/', '_\\1', $className)));
    }

    /**
     * Retrieve a last modified timestamp from property or index of $object.
     *
     * @param object|array
     * @return timestamp
     */
    protected static function getLastModified($object)
    {
        $name = static::$lastModifiedName;
        $value = null;
        if (is_array($object) && array_key_exists($name, $object)) {
            $value = $object[$name];
        }
        if (is_object($object) && property_exists($object, $name)) {
            $value = $object->$name;
        }
        if (is_string($value)) {
            $value = strtotime($value);
        }
        if ($value) {
            $value = gmdate('r', $value);
        }
        return $value;
    }
}
