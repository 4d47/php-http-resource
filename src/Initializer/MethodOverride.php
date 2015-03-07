<?php
namespace Http\Initializer;

class MethodOverride
{
    public function __invoke()
    {
        if (isset($_GET['_method'])) {
            $_SERVER['REQUEST_METHOD'] = strtoupper($_GET['_method']);
        }
    }
}
