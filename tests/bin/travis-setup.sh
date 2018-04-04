#!/usr/bin/env bash

# Remove Xdebug from PHP runtime for all PHP version except 7.1 to speed up builds.
# We need Xdebug enabled in the PHP 7.1 build job as it is used to generate code coverage.
phpenv config-rm xdebug.ini

composer install
