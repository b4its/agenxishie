FROM php:8.4-apache

# 1. Install dependencies & libicu-dev untuk intl
RUN apt-get update && apt-get install -y \
    libpng-dev libjpeg-dev libfreetype6-dev libzip-dev \
    libicu-dev \
    zip unzip git curl mariadb-client fonts-dejavu-core \
    # Tambahkan Node.js & NPM untuk compile Tailwind/Vite
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql gd zip intl

# 3. Apache: arahkan DocumentRoot ke public/ dan aktifkan mod_rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite

# 4. Git Fix
RUN git config --global --add safe.directory /var/www/html

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
