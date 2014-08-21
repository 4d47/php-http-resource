# \Http\Resource

> **The PHP paradox**: PHP is a web framework. Any attempt at using PHP will result in building a web framework.

## Install

```bash
composer require 4d47/http-resource:3.*
```

## Usage


```php
namespace App;

# First you define a 'class' of URLs.
# That's a set of documents that share the same structure of data and operations.
# Instances of the class will represents a specific URL.

class Product extends \Http\Resource {

    # The `$path` static variable holds the URL pattern that this resource
    # match.  When matched, the instance will have properties assigned with
    # the pattern variables.  Parenthesis can be used for optional variables,
    # colon denote a variable and a star matches anything. eg: `/foo((/:bar)/*)`

    public static $path = '/products/:name';

    # Then you implement HTTP methods, GET, POST, PUT, etc
    # to update the instance resource representation.
    # Server errors (5xx), client errors (4xx) and redirects (3xx) are sent by throwing
    # [http exceptions](http://github.com/4d47/php-http-exceptions).

    public function get() {
        if ($this->name == 'bazam')
            throw new \Http\NotFound();
        $this->price = 12;
    }

    # Implement any other methods you like
    public function __toString() {
        return sprintf("%s, %d$$", ucfirst($this->name), $this->price);
    }
}
```
    

Default `render` use scripts located in the `views` directory and named after the class name. Eg. `views/app/product.php`. The instance properties are  `extract` before being included. `$this` reference the resource itself, it can be used to assign properties or call helpers methods. `link` is used to reference back resource path. 

```php
<a href="<?= \App\Product::link($name) ?>">
    <?= $this ?>
</a>
```

If there is a file named `layout.php` in the views subpath, the first one will be used. 
The `$content` variable will hold the result of the first view and `$instance` will also be available.
Eg. using `views/layout.php`.

```php
<html>
<title><?= $instance->title ?></title>
<body><?= $content ?></body>
</html>
```
    
Finally you bootstrap everything in your `index.php` by handling 
the list of your resources.

```php
\Http\Resource::handle(['App\Product']);
```

See [4d47/php-start](https://github.com/4d47/php-start/) for a basic layout of the code.

