FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql && a2enmod rewrite
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
COPY . /var/www/html/
COPY docker-entrypoint.sh /usr/local/bin/lavalust-entrypoint
RUN chmod +x /usr/local/bin/lavalust-entrypoint && chown -R www-data:www-data /var/www/html
EXPOSE 80
ENTRYPOINT ["/usr/local/bin/lavalust-entrypoint"]
