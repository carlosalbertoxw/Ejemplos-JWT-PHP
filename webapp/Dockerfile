# Aplicación web de ejemplo JWT — PHP 8.5 + Apache
FROM php:8.5-apache

# Servir desde public/ (el resto del código queda fuera del document root)
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/000-default.conf \
        /etc/apache2/apache2.conf

# Configuración de producción. Sin php.ini, PHP usa sus valores por defecto:
# display_errors=On (un error mostraría rutas y trazas al cliente) y
# expose_php=On (la versión exacta de PHP viaja en X-Powered-By). Apache
# tampoco debe anunciar su versión ni la del sistema operativo.
# session.use_strict_mode rechaza IDs de sesión que el servidor no generó
# (mitiga la fijación de sesión).
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && printf 'expose_php = Off\nsession.use_strict_mode = 1\n' > "$PHP_INI_DIR/conf.d/zz-security.ini" \
    && printf 'ServerTokens Prod\nServerSignature Off\n' > /etc/apache2/conf-available/zz-security.conf \
    && a2enconf zz-security

COPY . /var/www/html/

EXPOSE 80
