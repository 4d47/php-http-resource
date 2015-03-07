<?php
namespace Http\Initializer;

class NoTrailingSlash
{
    public function __invoke()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // safeguard
        if ($uri === false) {
            throw new \Http\NotFound();
        }

        // begin opinionated here ...
        if (preg_match('|.+/$|', $uri)) {
            throw new \Http\MovedPermanently( rtrim($uri, '/') . (empty($_SERVER['QUERY_STRING']) ? "" : "?{$_SERVER['QUERY_STRING']}") );
        }
    }
}

