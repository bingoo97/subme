FROM php:8.3-apache

RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install mysqli gd
RUN a2enmod rewrite
RUN a2enmod headers
RUN sed -ri 's!/var/www/>\n\tOptions Indexes FollowSymLinks\n\tAllowOverride None!/var/www/>\n\tOptions Indexes FollowSymLinks\n\tAllowOverride All!g' /etc/apache2/apache2.conf
RUN sed -ri 's!/var/www/html!/var/www/html/public_html!g' /etc/apache2/sites-available/000-default.conf && \
    sed -ri 's!DocumentRoot /var/www/html!DocumentRoot /var/www/html/public_html!g' /etc/apache2/apache2.conf
