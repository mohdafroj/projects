FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
        freetype \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libpq \
        libsodium \
        libwebp \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libzip-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        gd \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN { \
        echo '[www]'; \
        echo 'clear_env = no'; \
    } > /usr/local/etc/php-fpm.d/zz-clear-env.conf

RUN { \
        echo '[www]'; \
        echo 'pm.max_children = 20'; \
        echo 'pm.start_servers = 4'; \
        echo 'pm.min_spare_servers = 2'; \
        echo 'pm.max_spare_servers = 8'; \
        echo 'request_terminate_timeout = 120s'; \
    } > /usr/local/etc/php-fpm.d/zz-pool-tuning.conf

RUN { \
        echo 'expose_php = Off'; \
    } > /usr/local/etc/php/conf.d/zz-security.ini

RUN { \
        echo 'upload_max_filesize = 512M'; \
        echo 'post_max_size = 512M'; \
        echo 'max_file_uploads = 20'; \
    } > /usr/local/etc/php/conf.d/zz-upload-limits.ini


WORKDIR /var/www/html
