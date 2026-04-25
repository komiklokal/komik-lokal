FROM php:8.2-fpm-alpine

# Install nginx dan bash
RUN apk add --no-cache nginx bash

# Install ekstensi PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Buat folder nginx
RUN mkdir -p /run/nginx

# Copy konfigurasi nginx
COPY nginx.conf /etc/nginx/http.d/default.conf

# Copy semua file project
COPY . /var/www/html/

# Hapus file SQL besar agar image lebih kecil
RUN rm -f /var/www/html/*.sql

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Entrypoint: set PORT dari Railway, jalankan php-fpm + nginx
RUN printf '#!/bin/sh\n\
export PORT=${PORT:-80}\n\
sed -i "s/listen 80/listen $PORT/g" /etc/nginx/http.d/default.conf\n\
php-fpm -D\n\
exec nginx -g "daemon off;"\n' > /entrypoint.sh \
    && chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/bin/sh", "/entrypoint.sh"]
