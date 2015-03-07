<?php
namespace Http;

class Bootstrap
{
    /**
     * @var array
     */
    private $initializers;

    /**
     * @var array
     */
    private $resources;

    /**
     * @var callable
     */
    private $make;

    /**
     * Callback to handle exceptions
     * Should not throw exception
     *
     * @var callback
     */
    private $onError;

    /**
     * @param array $initializers
     * @param array $resources
     * @param callable $make
     * @param callable $onError
     */
    public function __construct(array $initializers, array $resources, callable $make, callable $onError)
    {
        $this->initializers = $initializers;
        $this->resources = $resources;
        $this->make = $make;
        $this->onError = $onError;
    }

    /**
     * Route to a matching resource, calling the appropriate
     * HTTP method and render() the response.
     *
     * @param callable $make
     * @return void
     */
    public function start()
    {
        try {
            $this->initialize();

            $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

            // lookup $resources and call appropriate method
            foreach ($this->resources as $className) {
                $properties = $className::match($uri);
                if ($properties !== false) {
                    if (!method_exists($className, $_SERVER['REQUEST_METHOD'])) {
                        throw new MethodNotAllowed();
                    }
                    $resource = call_user_func($this->make, $className);
                    static::set_object_vars($resource, $properties);
                    $resource->init();
                    $resource->{$_SERVER['REQUEST_METHOD']}();
                    $resource->finalize();
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
            call_user_func($this->onError, $e);
            $e = new InternalServerError($e->getMessage(), $e);
        }
        if (isset($e)) {
            header("{$_SERVER['SERVER_PROTOCOL']} $e->code $e->reason");
            if ($e instanceof Error) { // not rendering redirects
                $error = new static();
                $error->error = $e;
                $error->renderTwoStepView($e);
            }
        }
    }

    /**
     * Invoke every initializers
     */
    private function initialize()
    {
        foreach ($this->initializers as $initClass) {
            $init = call_user_func($this->make, $initClass);
            $init();
        }
    }

    public static function set_object_vars($object, array $vars)
    {
        foreach ($vars as $name => $value) {
            $object->$name = $value;
        }
    }
}
