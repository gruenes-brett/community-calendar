#!/bin/bash

# needs composer to install phpunit:
# $ sudo apt install composer

composer install
vendor/bin/phpunit tests/*