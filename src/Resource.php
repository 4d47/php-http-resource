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
    public static $lastModifiedAttribute = 'lastModified';

    /**
     * The base directory of views script.
     *
     * @var string
     */
    public static $viewsDir = 'views';

    /**
     * Callback to handle exceptions
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
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $response = null;

            if ($path === false) {
                throw new NotFound();
            }

            // begin opinionated here ...
            if (preg_match('|.+/$|', $path)) {
                throw new MovedPermanently( rtrim($path, '/') . (empty($_SERVER['QUERY_STRING']) ? "" : "?{$_SERVER['QUERY_STRING']}") );
            }

            // method override
            if (isset($_GET['_method']) && in_array(strtoupper($_GET['_method']), array('GET', 'POST', 'PUT', 'DELETE', 'HEAD'))) {
                $_SERVER['REQUEST_METHOD'] = strtoupper($_GET['_method']);
            }

            // lookup $resources and call appropriate method
            foreach ($resources as $className) {
                if ($params = $className::match($path)) {
                    $resource = call_user_func($factory, $className);
                    if (!method_exists($resource, $_SERVER['REQUEST_METHOD'])) {
                        throw new MethodNotAllowed();
                    }
                    // assign non numeric params to resource and initialize
                    foreach ($params as $key => $param) {
                        if (!is_numeric($key))
                            $resource->$key = $param;
                    }
                    $resource->init();
                    $response = $resource->{ $_SERVER['REQUEST_METHOD'] }();
                    // caching headers
                    if ($lastModified = static::getLastModified($response)) {
                        header("Last-Modified: $lastModified");
                        if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $lastModified) {
                            throw new NotModified(null);
                        }
                    }

                    break;
                }
            }

            if (empty($resource)) {
                throw new NotFound();
            }

        } catch (NotModified $resource) {
            header("{$_SERVER['SERVER_PROTOCOL']} $resource->code $resource->reason");
        } catch (Redirection $resource) {
            header("Location: $resource->location", true, $resource->code);
            echo $resource->getMessage();
        } catch (Exception $resource) {
            $response = array('error' => $resource);
            header("{$_SERVER['SERVER_PROTOCOL']} $resource->code $resource->reason");
        } catch (\Exception $e) {
            call_user_func($resource::$onError, $e);
            $resource = new InternalServerError($e->getMessage(), $e);
            $response = array('error' => $resource);
            header("{$_SERVER['SERVER_PROTOCOL']} $resource->code $resource->reason");
        }
        if ($resource instanceof static) {
            $resource->render($response);
        } else if ($resource instanceof \Http\Error) {
            static::renderResource($resource, $response);
        }
    }

    /**
     * Test if the resource path match.
     *
     * @param string Request-URI to match against
     * @return array of URI pattern variables
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
        $route = static::$base . static::$path;
        foreach ($replacements as $pattern => $replacement) {
            $route = preg_replace($pattern, $replacement, $route);
        }
        preg_match("{^$route$}", $uri, $matches);
        if (empty($matches)) {
            return array();
        }
        // filter numeric key > 1 in results because it is ugly
        foreach ($matches as $key => $value) {
            if (is_integer($key) && $key >= 1) {
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
        $name = static::$lastModifiedAttribute;
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
