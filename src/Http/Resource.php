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
     * The Request-URI regex pattern this resource match.
     *
     * As a convenience the pattern is wrapped between "#^$pattern$#"
     * before it's used so it matches strictly and slashes does not
     * have to be escaped.  Also, the colon is treated as an additional
     * special character, allowing to write <code>:name</code>
     * instead of <code>(?P<name>[^/]+)</code>.
     *
     * @var string
     */
    public static $path = '/';

    /**
     * List of Request-URI segments prepended to the `$path`.
     * Eg. Adding 'admin' will match resource with /admin prefixed
     * to path and generate proper `link()` URLs.
     *
     * @var array
     */
    public static $base = array();

    /**
     * The layout filename to search for;
     * use <code>false</code> to disable.
     *
     * @var null|string
     */
    public static $layout = 'layout.php';

    /**
     * The base directory of views script.
     *
     * @var string
     */
    public static $viewsDir = 'views';

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
            if (!empty($_SERVER['REDIRECT_BASE'])) {
                $path = substr($path, strlen(rtrim($_SERVER['REDIRECT_BASE'], '/')));
            }

            // begin opinionated here ...
            if (preg_match('|.+/$|', $path)) {
                throw new MovedPermanently( rtrim($path, '/') . (empty($_SERVER['QUERY_STRING']) ? "" : "?{$_SERVER['QUERY_STRING']}") );
            }

            // method override
            if (isset($_GET['_method']) && in_array(strtoupper($_GET['_method']), array('GET', 'POST', 'PUT', 'DELETE', 'HEAD')))
                $_SERVER['REQUEST_METHOD'] = strtoupper($_GET['_method']);

            // lookup $resources and call appropriate method
            foreach ($resources as $className) {
                if ($params = $className::match($path)) {
                    $resource = $factory($className);
                    // if get and getLastModified() caching opportunity here ...
                    if (!method_exists($resource, $_SERVER['REQUEST_METHOD']))
                        throw new MethodNotAllowed();
                    // assign non numeric params to resource and initialize
                    foreach ($params as $key => $param) {
                        if (!is_numeric($key))
                            $resource->$key = $param;
                    }
                    $resource->init();
                    $response = $resource->{ $_SERVER['REQUEST_METHOD'] }();
                    break;
                }
            }

            if (empty($resource)) {
                throw new NotFound();
            }
            $resource::render($resource, $response);

        } catch (Redirection $e) {
            header("Location: $e->location", true, $e->code);
            echo $e->getMessage();
        } catch (Exception $resource) {
            $response = array('exception' => $resource);
            header("{$_SERVER['SERVER_PROTOCOL']} $resource->code $resource->reason");
        } catch (\Exception $resource) {
            $resource = new InternalServerError($resource->getMessage(), $resource);
            $response = array('exception' => $resource);
            header("{$_SERVER['SERVER_PROTOCOL']} $resource->code $resource->reason");
        }
        if ($resource instanceof \Http\Error) {
            static::render($resource, $response);
        }
    }

    /**
     * Test if the resource path match.
     *
     * @param string Request-URI to match against
     * @return array of URI pattern variables
     */
    public static function match($path)
    {
        $base = static::$base ? $base = call_user_func_array(array('static', 'path'), static::$base) : '';
        $pattern = preg_replace('#:([^/]+)#', '(?P<$1>[^/]+)', $base . static::$path);
        preg_match("#^$pattern$#", $path, $matches);
        return $matches;
    }

    /**
     * Link to another Resource instance
     */
    public static function link()
    {
        $segments = array_merge(static::$base, func_get_args());
        return call_user_func_array(array('static', 'url'), $segments);
    }

    /**
     * Build path relative to install directory
     */
    public static function path()
    {
        $base = !empty($_SERVER['REDIRECT_BASE']) ? rtrim($_SERVER['REDIRECT_BASE'], '/') : '';
        $path = '/' . implode('/', func_get_args());
        return "$base$path";
    }

    /**
     * Absolute URL version of path
     */
    public static function url()
    {
        $ssl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
        $protocol = 'http' . ($ssl ? 's' : '');
        $port = $_SERVER['SERVER_PORT'];
        $port = ((!$ssl && $port == '80') || ($ssl && $port == '443')) ? '' : ":$port";
        # this is ternary operator festival, please dont blink
        $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
        $host = strstr($host, ':') ? $host : $host . $port;
        $uri = call_user_func_array(array('static', 'path'), func_get_args());
        return "$protocol://$host$uri";
    }

    /**
     * Called after pattern variables are assigned.
     */
    public function init()
    {
        ;
    }

    /**
     * The date and time at which this resource was last modified.
     *
     * @return \DateTime
     */
    public function getLastModified()
    {
        return new \DateTime();
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
        ;
    }

    /**
     * Render a $resource's $response data to the browser.
     * Provides a basic two step view implementation.
     *
     * @param \Http\Resource|\Http\Exception
     * @param mixed data resulted from the resource method
     * @return void
     */
    protected static function render($resource, $response)
    {
        // first step, logical presentation
        $resourceClass = get_class($resource);
        ob_start();
        do {
            $name = static::classToPath($resourceClass);
            $path = static::$viewsDir . "/$name.php";
            if (file_exists($path)) {
                static::renderFile($path, $response, '');
                break;
            }
            // try parent classes
        } while (false != ($resourceClass = get_parent_class($resourceClass)));
        $content = ob_get_clean();

        // second step, layout formatting
        if (empty($resource::$layout)) {
            echo $content;
        } else {
            $name = static::classToPath(get_class($resource));
            do {
                $name = dirname($name);
                if (file_exists(static::$viewsDir . "/$name/{$resource::$layout}")) {
                    static::renderFile(static::$viewsDir . "/$name/{$resource::$layout}", $response, $content);
                    break;
                }
            } while ($name != '.');
        }
    }

    /**
     * Require a file, controlling it's symbol table.
     * todo: make the function variadic instead of $content,
     * eg. renderFile(filename, array $params, array $params, ...)
     *
     * @param string Filename
     * @param array Variables to be extracted
     * @param string $content variable
     */
    protected static function renderFile()
    {
        extract($GLOBALS);
        extract((array) func_get_arg(1));
        $content = func_get_arg(2);
        require func_get_arg(0);
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
     * Utility method to throw an exception inline. eg.
     *
     *     $foo = get($id) ?: $this->raise('Http\NotFound');
     *
     * @see http://stackoverflow.com/questions/1211237/php-or-statement-on-instruction-fail-how-to-throw-a-new-exception#1211497
     */
    protected function raise($className, $message = null)
    {
        throw new $className($message);
    }
}
