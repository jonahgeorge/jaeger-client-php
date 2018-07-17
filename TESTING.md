# PHP OpenTracing API - PHP Version Testing

## Current PHP Support
| version | status |
|---------|--------|
| 5.6     | 𝙓      |
| 7.0     | ✔      |
| 7.1     | ✔      |
| 7.2     | ✔      |

## Run Tests for Supported Versions
Install [Docker](https://docs.docker.com/install/)
```sh
$ docker run --rm -it -v $(pwd):/usr/app php:5.6 ./usr/app/scripts/php-test.sh

$ docker run --rm -it -v $(pwd):/usr/app php:7.0 ./usr/app/scripts/php-test.sh

$ docker run --rm -it -v $(pwd):/usr/app php:7.1 ./usr/app/scripts/php-test.sh

$ docker run --rm -it -v $(pwd):/usr/app php:7.2 ./usr/app/scripts/php-test.sh
```

## License

[MIT License](./LICENSE).
