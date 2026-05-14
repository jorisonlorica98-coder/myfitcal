FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql mysqli \
    && a2enmod rewrite \
    && sed -i 's/^#LoadModule mpm_prefork/LoadModule mpm_prefork/' /etc/apache2/mods-available/mpm_prefork.load \
    && a2dismod mpm_event 2>/dev/null || true

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html

EXPOSE 80