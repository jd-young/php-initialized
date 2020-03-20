# php-initialized

A utility to check for uninitialised variables in PHP code. 

Unlike other languages PHP will create a fresh new unitialised variable when you reference it, so if you misspell a
variable name, then you actually have two similarly spelled variables. PHP gives no warning, which is similar to other
languages (Perl, Bash, _etc_.), however, unlike these other languages which provide some mechanism to switch the
default behaviour off (`use strict` in perl, or `set -u` in bash), PHP has no such mechanism.

As a programmer who uses perl and bash, and routinely use the above mentioned 'switch-off' constructs, I am
continuously frustrated when programming in PHP, simply because my spelling is bad. :-(

Hence the need for `php-initialized`, which reports all instances of uninitialised variables.

## Getting Started

`php-initialized` runs as a command-line [PHP](https://www.php.net/) script, and so requires PHP, but since you're
probably a PHP programmer, you probably have this installed already.

Download this `php-initialize` project into an empty directory.  

### Unix bases systems

On a Unix based system, you may need to make the `php-initialized.php` script executable.  The shebang at the top of 
the script should reference the PHP binary.  If not, then you may have to edit the shebang.

Run like this...

```
php-initialized.php <your-php-file>...
```


### Windows

Run like this...

```
php php-initialized.php <your-php-file>...
```

## Running the tests

The unit tests for `php-initialized` are in the `tests` directory. The test runner is `run-tests.php`, and it processes 
all the test files in the `tests` directory with a `.phpt` extension.  Other `.php` files are include files, used to 
test PHP's `include` functionality.  The test files are numbered for easy reference.

### Test file format

Each test file has three sections; the title / test description, the PHP content to test, and the expected output.  
These are delimited by `--TEST--`, `--FILE--`, and `--EXPECTF--` respectively (I don't know what the 'F' at the end of 
'EXPECTF' means).

For example, `1-assignment.phpt` looks like this...

```php
--TEST--
Assignment
--FILE--
<?php
$a = 5;
?>
--EXPECTF--
```

This is a simple test that checks that `php-initialized` detects that the variable `$a` is initialised, and there is no 
expected output.


## Contributing

Please feel free to contribute, whether it be bug reports, feature requests, or code changes.


## Versioning

We use [SemVer](http://semver.org/) for versioning. For the versions available, see the [tags on this
repository](https://github.com/jd-young/php-initialized/tags).

## Authors

* **[Jakub Vrána](https://github.com/vrana)** - *Initial work* - [vrana.cz](https://www.vrana.cz/)
* **[John Young](https://github.com/jd-young)** - *Bug fixes*

See also the list of [contributors](https://github.com/jd-young/php-initialized/contributors) who participated in this
project.

## License

This project is dual-licenced under the:

* [Apache License, Version 2.0](http://www.apache.org/licenses/LICENSE-2.0)
* [GNU General Public License, version 2](http://www.gnu.org/licenses/gpl-2.0.html)

Either is okay.

## Acknowledgments

* The original author - Jakub Vrána
