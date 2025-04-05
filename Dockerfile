FROM php:8.3-fpm

# Устанавливаем необходимые зависимости
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    curl \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libmagickwand-dev \
    && docker-php-ext-configure zip \
    && docker-php-ext-install pdo pdo_pgsql zip exif \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && pecl config-set preferred_state beta \
    && pecl install imagick \
    && docker-php-ext-enable imagick

# Настройка PHP для загрузки больших файлов
RUN { \
        echo 'upload_max_filesize = 50M'; \
        echo 'post_max_size = 55M'; \
        echo 'memory_limit = 256M'; \
        echo 'max_execution_time = 36000'; \
        echo 'max_input_time = 36000'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

# Устанавливаем Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /var/www/html

COPY . .

# Запускаем Composer
RUN composer install --no-dev --optimize-autoloader

CMD ["php-fpm"]
