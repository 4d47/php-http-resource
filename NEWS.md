
# 4.0.0

- Add Bootstrap class so it can be injected
- Add Initializers, a configurable list of class that is invoked on start

# 3.0.0

- Add NEWS.md file
- Add `$this` to views
- Change `link(array)` to `link(...)`
- Change `render(mixed)` to `render()` (render $this)
- Change `mixed get()` to `void get()`
- Change `$lastModifiedName` to `$lastModified`
- Remove `static::$viewsVars`
- Fix match to return urldecoded pattern variables

# 2.1.0

- Add `static::$viewsVars`
- Change from `$lastModifiedAttribute` to `$lastModifiedName`
- Improve code readability from scrutinizer insights 

# 2.0.0

- Add README.md with basic usage
- Add `static::$onError` handler
- Change to semver
- Change from PSR0 to PS4 autoload
- Change `renderFile()` to `partial()`
- Improve test coverage

# 1.2

- Add travis file to run tests on multiple PHP versions
- Fix bug on PHP 5.3

# 1.1

- Update to http-exceptions 1.x

# 1.0

- Add if_modified_since support
