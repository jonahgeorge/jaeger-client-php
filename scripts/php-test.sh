#!/bin/sh

set -e

# install os dependencies
apt-get update && apt-get install -y git wget unzip zip

# install php extensions
docker-php-ext-install bcmath sockets

# install composer
EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_SIGNATURE="$(php -r "echo hash_file('SHA384', 'composer-setup.php');")"

if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]
then
    >&2 echo 'ERROR: Invalid installer signature'
    rm composer-setup.php
    exit 1
fi

php composer-setup.php --quiet
rm composer-setup.php

cd /usr/app

# install application dependencies
php composer.phar install

# run tests
vendor/bin/phpunit