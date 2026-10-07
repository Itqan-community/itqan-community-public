# Flarum 1.8 runs on PHP 8.1–8.3. Pinned to 8.3 rather than "latest": on 8.4
# the dependency tree emits a wall of implicit-nullable deprecations, which
# buries real errors in the log.
FROM php:8.3-apache

# gd, pdo_mysql, zip and exif are the extensions Flarum and the installed
# extensions need beyond what this image already carries (curl, dom, fileinfo,
# json, mbstring, openssl and tokenizer are built in).
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libpng-dev libjpeg62-turbo-dev libfreetype6-dev libzip-dev \
        unzip git default-mysql-client; \
    docker-php-ext-configure gd --with-freetype --with-jpeg; \
    docker-php-ext-install -j"$(nproc)" gd pdo_mysql zip exif; \
    apt-get clean; \
    rm -rf /var/lib/apt/lists/*

# Flarum's .htaccess needs rewriting, and the document root is public/ — the
# rest of the tree, config.php included, must stay unreachable.
RUN a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
       > /etc/apache2/conf-available/flarum.conf \
    && a2enconf flarum

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/flarum.ini
COPY entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

WORKDIR /var/www/html

ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
