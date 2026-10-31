FROM php:8.3-apache

# PHP needs the MySQL extension to talk to Aiven. Apache needs these two modules.
RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers rewrite

# Production PHP settings: errors go to the log, not onto visitors' screens.
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Don't reveal the Apache version to visitors.
RUN printf "ServerTokens Prod\nServerSignature Off\n" > /etc/apache2/conf-available/hide-version.conf \
    && a2enconf hide-version

# Render tells the site which port to listen on through the PORT variable.
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/:80>/:${PORT}>/' /etc/apache2/sites-available/000-default.conf

# Copy the whole project into the web folder.
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000