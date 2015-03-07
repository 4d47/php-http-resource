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
     * Should the layout be used in render.
     *
     * @var boolean
     */
    public static $layout = true;

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
     * Test if the resource path match.
     *
     * @param string Request-URI to match against
     * @return false|array of URI pattern variables
     */
    public static function match($uri)
    {
        $replacements = [
            '/\(/' => '(',
            '/\)/' => ')?',
            '/\./' => '\.',
            '/:(\w+)/' => '(?P<$1>[^./]+)',
            '/\*/' => '(?P<rest>.*?)',
        ];
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
     * Called right before method call.
     */
    public function init()
    {
        ;
    }

    /**
     * Called right after method call.
     */
    public function finalize()
    {
        if ($this->lastModified) {
            $lastModified = gmdate('r', $this->lastModified);
            header("Last-Modified: $lastModified");
            if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $lastModified) {
                throw new NotModified(null);
            }
        }
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
        throw new MethodNotAllowed();
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
                    $content = $this->partial($file, ['content' => $content]);
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
