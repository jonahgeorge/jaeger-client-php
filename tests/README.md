# Jaeger Bindings for PHP OpenTracing API: Unit Tests

Welcome to the Jaeger Testing Suite.

This folder includes all the unit tests that test Jaeger components, ensuring that you enjoy a bug free library.

## Current PHP Support

| version | status |
|---------|--------|
| 7.0     | ✔      |
| 7.1     | ✔      |
| 7.2     | ✔      |


## Getting Started

This testing suite uses [Travis CI](https://travis-ci.org/) for each run.
Every commit pushed to this repository will queue a build into the continuous integration service and will run all tests
to ensure that everything is going well and the project is stable.

The testing suite can be run on your own machine. The main dependency is [PHPUnit](https://phpunit.de/)
which can be installed using [Composer](https://getcomposer.org/):

```bash
# run this command from project root
$ composer install
```

Then run the tests by calling command from the terminal as follows:

```bash
$ composer test
```

## Run Tests for Supported Versions

There is also an ability to run tests for different PHP versions. To achieve this we offer use
[docker](https://docs.docker.com/install/)-based approach:

```bash

$ docker run --rm -it -v $(pwd):/usr/app php:7.0 ./usr/app/tests/php-test.sh

$ docker run --rm -it -v $(pwd):/usr/app php:7.1 ./usr/app/tests/php-test.sh

$ docker run --rm -it -v $(pwd):/usr/app php:7.2 ./usr/app/tests/php-test.sh
```
