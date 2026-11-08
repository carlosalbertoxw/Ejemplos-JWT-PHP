# API de ejemplo JWT — PHP 8.5 + Apache
FROM php:8.5-apache

# Servir desde public/ (el resto del código queda fuera del document root)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf \
        /etc/apache2/apache2.conf \
    && a2enmod rewrite \
    # Permitir que el .htaccess de public/ aplique sus reglas de rewrite
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n</Directory>\n' \
        > /etc/apache2/conf-available/docroot.conf \
    && a2enconf docroot

COPY . /var/www/html/

# Carpeta de datos escribible para la base SQLite
RUN mkdir -p /var/www/html/data && chown -R www-data:www-data /var/www/html/data

EXPOSE 80
