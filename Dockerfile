FROM php:8.4-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpng-dev libjpeg62-turbo-dev libwebp-dev libonig-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp \
 && docker-php-ext-install pdo_mysql gd mbstring \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /var/www/html/public>\n AllowOverride All\n Require all granted\n</Directory>\n' > /etc/apache2/conf-available/artesanias.conf \
 && a2enconf artesanias
RUN printf 'upload_max_filesize=5M\npost_max_size=7M\ndisplay_errors=Off\nlog_errors=On\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/artesanias.ini
COPY . /var/www/html
RUN mkdir -p /var/www/html/storage/uploads /var/www/html/storage/logs \
 && chown -R www-data:www-data /var/www/html/storage
