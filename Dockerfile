# Use FrankenPHP (Caddy + PHP)
FROM dunglas/frankenphp

# # Install system dependencies
# RUN apt-get update && apt-get install -y \
#     zip \
#     unzip \
#     && rm -rf /var/lib/apt/lists/*

# Configure PHP extensions
RUN install-php-extensions gd

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY public_html/ /var/www/html/

# Copy FrankenPHP (Caddy) configuration
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

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
