#!/bin/bash

crontab /etc/cron.d/app-cron

cron

docker-php-entrypoint php-fpm
