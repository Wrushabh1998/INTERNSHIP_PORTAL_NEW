# ─── Stage: Production ────────────────────────────────────────────────────────
FROM php:8.2-apache

# System deps
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        gd \
        pdo \
        pdo_mysql \
        mysqli \
        zip \
        opcache

# Enable Apache mod_rewrite
RUN a2enmod rewrite headers

# PHP production settings
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.memory_consumption=192'; \
    echo 'opcache.max_wasted_percentage=10'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

RUN { \
    echo 'upload_max_filesize = 10M'; \
    echo 'post_max_size = 12M'; \
    echo 'memory_limit = 256M'; \
    echo 'max_execution_time = 60'; \
    echo 'display_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'date.timezone = Asia/Kolkata'; \
} > /usr/local/etc/php/conf.d/app.ini

# Apache VirtualHost — serve from /var/www/html (no subdirectory prefix)
RUN { \
    echo '<VirtualHost *:80>'; \
    echo '    DocumentRoot /var/www/html'; \
    echo '    <Directory /var/www/html>'; \
    echo '        AllowOverride All'; \
    echo '        Require all granted'; \
    echo '        Options -Indexes +FollowSymLinks'; \
    echo '    </Directory>'; \
    echo '    ErrorLog ${APACHE_LOG_DIR}/error.log'; \
    echo '    CustomLog ${APACHE_LOG_DIR}/access.log combined'; \
    echo '</VirtualHost>'; \
} > /etc/apache2/sites-available/000-default.conf

# Copy application code
COPY . /var/www/html/

# Create upload directories & set permissions
RUN mkdir -p /var/www/html/uploads/profile \
             /var/www/html/uploads/attendance \
             /var/www/html/uploads/documents \
             /var/www/html/uploads/reports \
             /var/www/html/uploads/leaves \
             /var/www/html/uploads/announcements \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -name "*.php" -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 80

CMD ["apache2-foreground"]
