FROM php:8.2-apache

# Fix: Nonaktifkan MPM yang konflik, aktifkan hanya mpm_prefork
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true \
    && a2enmod mpm_prefork

# Install ekstensi mysqli dan pdo_mysql
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Aktifkan mod_rewrite untuk Apache
RUN a2enmod rewrite

# Copy semua file project ke dalam container
COPY . /var/www/html/

# Hapus file SQL besar agar tidak masuk image
RUN rm -f /var/www/html/*.sql

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Apache config: izinkan .htaccess override
RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/allow-override.conf \
    && a2enconf allow-override

# Script entrypoint: set Apache port dari $PORT Railway
RUN echo '#!/bin/bash\n\
export PORT=${PORT:-80}\n\
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf\n\
sed -i "s/:80>/:$PORT>/" /etc/apache2/sites-enabled/000-default.conf\n\
apache2-foreground' > /entrypoint.sh \
    && chmod +x /entrypoint.sh

EXPOSE 80

CMD ["/bin/bash", "/entrypoint.sh"]
