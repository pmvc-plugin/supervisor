#!/bin/bash

DIR="$( cd "$(dirname "$0")" ; pwd -P )"
PLUGIN_NAME="supervisor"

docker run --rm \
  -v $DIR:/var/www/html \
  -v $DIR:/var/www/${PLUGIN_NAME} \
  --name phpunit hillliu/pmvc-phpunit:5.6 \
  phpunit --no-configuration --bootstrap ./include_test.php ./tests-legacy/

# docker-compose run --rm phpunit phpunit --no-configuration --bootstrap ./include_test.php ./tests-legacy/test.php
