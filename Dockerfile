FROM php:8.4-apache

# Demo con SQLite para Render Free.
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev libsqlite3-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install pdo_sqlite gd mbstring \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public \
    APP_ENV=production \
    APP_BASE_PATH="" \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/storage/demo.sqlite \
    SHOW_DEMO_ACCOUNTS=0

RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && printf '%s\n' '<Directory /var/www/html/public>' ' Options -Indexes' ' AllowOverride None' ' Require all granted' ' DirectoryIndex index.php' ' RewriteEngine On' ' RewriteCond %{REQUEST_FILENAME} !-f' ' RewriteCond %{REQUEST_FILENAME} !-d' ' RewriteRule ^ index.php [QSA,L]' ' <FilesMatch "^\\.">' '  Require all denied' ' </FilesMatch>' '</Directory>' > /etc/apache2/conf-available/artesanias.conf \
 && a2enconf artesanias

RUN printf '%s\n' 'upload_max_filesize=5M' 'post_max_size=7M' 'display_errors=Off' 'log_errors=On' 'expose_php=Off' > /usr/local/etc/php/conf.d/artesanias.ini

# Copiar solo el codigo y los recursos, sin bases locales ni archivos .env.
COPY app/ ./app/
COPY bin/ ./bin/
COPY database/ ./database/
COPY public/ ./public/

RUN mkdir -p storage/uploads storage/logs \
 && chown -R www-data:www-data storage

EXPOSE 80
CMD ["sh", "-c", "set -eu; if [ ! -f \"$DB_DATABASE\" ]; then php bin/install.php --demo; fi; chown -R www-data:www-data storage; exec apache2-foreground"]
