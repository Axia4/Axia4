# Use official PHP image with Apache
FROM php:8.2-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Configure PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd

# Enable Apache modules
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY public_html/ /var/www/html/

# Create DATA directory with proper permissions
RUN mkdir -p /DATA && \
    chown -R www-data:www-data /DATA && \
    chmod -R 755 /DATA

# Set permissions for web directory
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Configure PHP settings
RUN echo "session.cookie_lifetime = 604800" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "session.gc_maxlifetime = 604800" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "upload_max_filesize = 500M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "post_max_size = 500M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "date.timezone = UTC" >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "display_errors = off" >> /usr/local/etc/php/conf.d/custom.ini

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]
