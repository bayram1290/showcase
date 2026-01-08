FROM php:8.2-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    oniguruma-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    postgresql-dev \
    libzip-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    zip \
    opcache \
    intl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/demo_a/backend

RUN if ! getent passwd www-data > /dev/null 2>&1; then \
        addgroup -g 1000 www-data && \
        adduser -u 1000 -G www-data -s /bin/sh -D www-data; \
    elif ! getent group www-data > /dev/null 2>&1; then \
        addgroup -g 1000 www-data; \
    else \
        echo "www-data user and group already exist, skipping creation"; \
    fi

RUN mkdir -p /var/ww/demo_a \
    && mkdir -p backend/storage \
    && mkdir -p backend/bootstrap/cache \
    && chown -R www-data:www-data backend \
    && chmod -R 755 backend \
    && chmod -R 775 backend/storage \
    && chmod -R 775 backend/bootstrap/cache

WORKDIR /var/www/demo_a/backend

USER www-data

EXPOSE 9000
CMD [ "php-fpm" ]
