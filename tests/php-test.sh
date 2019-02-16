#!/usr/bin/env bash

# trace ERR through pipes
set -o pipefail

# trace ERR through 'time command' and other functions
set -o errtrace

# set -u : exit the script if you try to use an uninitialised variable
set -o nounset

# set -e : exit the script if any statement returns a non-true return value
set -o errexit

# to avoid message:
# "Do not run Composer as root/super user! See https://getcomposer.org/root for details"
export COMPOSER_ALLOW_SUPERUSER=1

export TERM=xterm-256color

echo "[INFO]: Install OS dependencies..."
apt-get update -yq > /dev/null 2>&1
apt-get install -yq git wget unzip zip > /dev/null 2>&1

echo "[INFO]: Install PHP extensions..."
docker-php-ext-install bcmath sockets > /dev/null 2>&1
pecl install hrtime > /dev/null 2>&1
docker-php-ext-enable hrtime > /dev/null 2>&1

echo "[INFO]: Install Xdebug to enable code coverage..."
pecl install xdebug > /dev/null 2>&1
docker-php-ext-enable xdebug > /dev/null 2>&1

cd /tmp

echo "[INFO]: Install Composer..."
EXPECTED_SIGNATURE="$(wget -q -O - https://composer.github.io/installer.sig)"
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
ACTUAL_SIGNATURE="$(php -r "echo hash_file('SHA384', 'composer-setup.php');")"

if [ "$EXPECTED_SIGNATURE" != "$ACTUAL_SIGNATURE" ]; then
    >&2 echo '[ERROR]: Invalid installer signature'
    rm composer-setup.php
    exit 1
fi

php composer-setup.php --quiet
rm composer-setup.php

# this step is required to be able to overwrite composer.lock
cp -R /usr/app /usr/tests

cd /usr/tests
rm -f composer.lock

echo "[INFO]: Install library dependencies..."
php /tmp/composer.phar install \
        --no-interaction \
        --no-ansi \
        --no-progress \
        --no-suggest

echo -e "[INFO]: Run tests...\n"
/tmp/composer.phar test
