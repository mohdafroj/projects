# Use the official PHP 8.4 Alpine image as the base
FROM php:8.4-cli-alpine
# Install system dependencies and the install-php-extensions script
RUN apk --no-cache add \
    bash \
    curl \
    git \
    libpng-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev \
    icu-dev \
    zlib-dev \
    libzip-dev \
    sqlite-dev \
    autoconf \
    gcc \
    g++ \
    make \
    pkgconfig \
    re2c \
    oniguruma-dev \
    openssl-dev \
    npm \
    && curl -sSL https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -o /usr/local/bin/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions
# Install ext-mongodb 1.21.2 via PECL
RUN pecl install mongodb-1.21.2 && docker-php-ext-enable mongodb
# Install PHP extensions
RUN install-php-extensions gd pdo pdo_mysql mbstring zip bcmath
# Install Composer globally
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
# Set working directory
WORKDIR /var/www/html/

# Create the Laravel project during build (Laravel 11)
# This will create app files in /var/www/html and install vendor.
# RUN composer create-project laravel/laravel:"11.*" . --no-interaction --prefer-dist

# Copy your Laravel app (optional – if you’re building locally)
COPY . .
#RUN php artisan optimize:clear
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache
# Install PHP EXIF extension
RUN docker-php-ext-install exif
RUN apk add --no-cache php-exif
# Mark Git repo as safe
RUN git config --global --add safe.directory /var/www/html
# Now install PHP dependencies
RUN composer install

RUN php artisan optimize
# Now generate swagger docs
RUN composer dump-autoload

RUN php artisan optimize
RUN php artisan l5-swagger:generate --all || true

# Expose Laravel dev server port
EXPOSE 8000
# Set Laravel app key automatically (optional)
# This can be useful if you are spinning up the container from scratch
# and want to generate a new app key each time.
# CMD ensures it runs on container start
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]