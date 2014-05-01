# \Http\Resource

    The PHP paradox: PHP is a web framework. Any attempt at using PHP will result in building a web framework.

So one day I decided to not choose any web framework. Just for fun. I was then left missing three things:

- Clean URLs
- A front controller
- 2 step views


    <?php
    namespace App;

    // First you define and name a class of URLs; that is a set of documents
    // that share the same structure of data and operations. Instances of the
    // class will represents a specific URL.

    class Product extends \Http\Resource {

        // The `$path` static variable holds the URL pattern that this resource
        // match.  If matched, the instance will have it's properties assigned 
        // with the pattern variables.  Parens can be used for optional variables
        // and star to match anything. eg. `/foo((/:bar)/*)`

        public static $path = '/products/:name';

        // The `init` method is called right after the resource is constructed 
        // and all it's variables are assigned to it.

        public function init() {
            $this->name;
        }

        // Then you implement HTTP methods, GET, POST, PUT, etc
        // to return the data for the view. Server errors (5xx), client errors (4xx)
        // and redirects (3xx) are sent by throwing [http exception](http://github.com/4d47/php-http-exceptions).

        public function get() {
            if ($this->name == 'bazam')
                throw new \Http\NotFound();
            return array('name' => $this->name);
        }
    }

Views are located in the `views` directory and are named after the class name. Eg. `views/app/product.php`. The data is `extract` before being included.

    <?php $title = $name; ?>
    <p><?= $name ?></p>

If there is a file named `layout.php` in the view subpath, it will be used. 
The `$content` variable will hold the result of the first view. Eg using `views/layout.php`.

    <html>
    <head><title><?= htmlspecialchars($title) ?></title></head>
    <body><?= $content ?></body>
    </html>
    
Finally, you bootstrap everything in your `index.php` and handle 
the list of your resources.

    <?php
    \Http\Resource::handle(array('App\Product'));
