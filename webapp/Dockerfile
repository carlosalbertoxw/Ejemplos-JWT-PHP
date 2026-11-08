# Aplicación web de ejemplo JWT — PHP 8.5 + Apache
FROM php:8.5-apache

# Servir desde public/ (el resto del código queda fuera del document root)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf \
        /etc/apache2/apache2.conf

COPY . /var/www/html/

EXPOSE 80
