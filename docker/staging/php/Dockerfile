FROM php:8.3-fpm

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim optipng pngquant gifsicle \
    vim nano mc \
    unzip \
    git \
    curl \
    libzip-dev \
    libpq-dev \
    libonig-dev \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

RUN docker-php-ext-install pdo pdo_mysql pdo_pgsql mbstring zip exif pcntl

RUN apt-get install vim -y
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/www/html/storage/logs/cron && \
    chown -R www-data:www-data /var/www/html/storage/logs && \
    chmod -R 755 /var/www/html/storage/logs

COPY --chown=www-data:www-data . /var/www/html

COPY docker/production/app-cron /etc/cron.d/app-cron
COPY docker/production/scripts/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

RUN chmod +x /usr/local/bin/docker-entrypoint.sh

RUN chmod 0644 /etc/cron.d/app-cron

RUN touch /var/log/cron.log && \
    chown www-data:www-data /var/log/cron.log

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

RUN groupadd --force -g 1000 www-data

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php composer-setup.php --install-dir=/usr/local/bin --filename=composer \
    && php -r "unlink('composer-setup.php');"

USER www-data

RUN composer install

USER root

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
