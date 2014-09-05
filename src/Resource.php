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
     * List of allowed HTTP methods.
     *
     * @var array
     */
    public static $allowedRequestMethods = array('GET', 'POST', 'PUT', 'DELETE', 'HEAD');

    /**
     * Last modified timestamp
     *
     * @var uint unix timestamp
     */
    public $lastModified;

    /**
     * Error generated while accessing resource.
     *
     * @var \Http\Error
     */
    public $error;

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

            if (!in_array($_SERVER['REQUEST_METHOD'], static::$allowedRequestMethods)) {
                throw new MethodNotAllowed();
            }

            // lookup $resources and call appropriate method
            foreach ($resources as $className) {
                $props = $className::match($uri);
                if ($props !== false) {
                    $resource = call_user_func($factory, $className);
                    $resource->dispatch($_SERVER['REQUEST_METHOD'], $props);
                    $resource->render();
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
                $error = new static();
                $error->error = $e;
                $error->renderTwoStepView($e);
            }
        }
    }

    /**
     * Initialize resource with $properties and call appropiate $method.
     *
     * @param string $method
     * @param array $properties
     */
    public function dispatch($method, array $properties)
    {
        if (!method_exists($this, $method)) {
            throw new MethodNotAllowed();
        }
        // assign properties to resource and initialize
        foreach ($properties as $key => $value) {
            $this->$key = $value;
        }
        $this->init();
        $this->$method();
        // caching headers
        if ($this->lastModified) {
            $lastModified = gmdate('r', $this->lastModified);
            header("Last-Modified: $lastModified");
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $lastModified) {
                throw new NotModified(null);
            }
        }
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
            } else {
                $matches[$key] = urldecode($matches[$key]);
            }
        }
        return $matches;
    }

    /**
     * Substitutes $vars in the $path.
     *
     * @param ... $vars
     * @return string
     */
    public static function link()
    {
        $vars = func_get_args();
        $result = static::$base . static::$path;
        // Substitute var in path pattern
        foreach ($vars as $i => $var) {
            $result = preg_replace('/(:\w+)/', $var, $result, 1, $count);
            if ($count == 0) {
                $var = implode('/', array_slice($vars, $i));
                $result = preg_replace('/\*/', $var, $result, 1, $count);
                if ($count == 0) {
                    throw new \LogicException("Cannot match var pattern for '$var'");
                }
            }
        }
        // Clean out any optional parts
        $result = preg_replace('/\([^(]*[:*].*\)/', '', $result);
        $result = preg_replace('/[()]/', '', $result);
        // Fail if there is unsubstituted params
        if (preg_match('/[:*]/', $result)) {
            throw new \LogicException("Incomplete link: $result");
        }
        return $result;
    }

    /**
     * Called right after properties are assigned.
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
        $this->get();
    }

    /**
     * @see http://www.w3.org/Protocols/rfc2616/rfc2616-sec9.html#sec9.3
     */
    public function get()
    {
        throw new \Http\MethodNotAllowed();
    }

    /**
     * Renders a resource using `renderTwoStepView` strategy.
     */
    public function render()
    {
        $this->renderTwoStepView($this);
    }

    /**
     * Render a $resource's to the browser using
     * a basic two step view implementation.
     *
     * @param \Http\Resource|\Http\Exception $object
     */
    public function renderTwoStepView($object)
    {
        // first step, logical presentation
        $resourceClass = get_class($object);
        $content = '';
        do {
            $name = static::classToPath($resourceClass);
            $file = static::$viewsDir . "/$name.php";
            if (file_exists($file)) {
                $content = $this->partial($file, $object);
                break;
            }
        // try parent classes
        } while (false != ($resourceClass = get_parent_class($resourceClass)));

        // second step, layout formatting
        if (static::$layout) {
            $name = static::classToPath(get_class($object));
            do {
                $name = dirname($name);
                $file = static::$viewsDir . "/$name/layout.php";
                if (file_exists($file)) {
                    $content = $this->partial($file, array('content' => $content));
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
    protected function partial()
    {
        ob_start();
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
}
