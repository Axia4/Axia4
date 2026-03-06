# ──────────────────────────────────────────────────────────────────────────────
# Stage 1 – Install PHP dependencies (Composer)
# ──────────────────────────────────────────────────────────────────────────────
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ──────────────────────────────────────────────────────────────────────────────
# Stage 2 – Build frontend assets (Node.js)
# vendor/ must be present so that the "adios" npm alias can resolve.
# ──────────────────────────────────────────────────────────────────────────────
FROM node:20-alpine AS assets

WORKDIR /build

COPY package.json ./
COPY webpack.config.js tailwind.config.js babel.config.js tsconfig.json ./
COPY src/ ./src/
COPY --from=composer /app/vendor ./vendor

RUN npm install && npm run build

# ──────────────────────────────────────────────────────────────────────────────
# Stage 3 – Production image (FrankenPHP / Caddy + PHP)
# ──────────────────────────────────────────────────────────────────────────────
FROM dunglas/frankenphp

# PHP extensions
RUN install-php-extensions gd opcache pdo pdo_mysql

WORKDIR /var/www/html

# Copy the ADIOS application source
COPY composer.json env.php index.php ./
COPY src/ ./src/
COPY public_html/static/ ./public_html/static/

# Copy Composer-managed vendor directory
COPY --from=composer /app/vendor ./vendor

# Copy compiled frontend assets
COPY --from=assets /build/assets ./assets

# Copy legacy public_html (kept for backwards compatibility during migration)
COPY public_html/ ./public_html/

# Copy FrankenPHP / Caddy configuration
COPY docker/Caddyfile /etc/frankenphp/Caddyfile

# Create DATA directory with proper permissions
RUN mkdir -p /DATA && \
    chown -R www-data:www-data /DATA && \
    chmod -R 755 /DATA

# Set permissions for web directory
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# PHP runtime configuration
RUN echo "session.cookie_lifetime = 604800"           >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "session.gc_maxlifetime = 604800"             >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "upload_max_filesize = 500M"                  >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "post_max_size = 500M"                        >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "memory_limit = 512M"                         >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "max_execution_time = 300"                    >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "date.timezone = UTC"                         >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "display_errors = off"                        >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "opcache.enable = 1"                          >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "opcache.memory_consumption = 128"            >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "opcache.interned_strings_buffer = 8"         >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "opcache.max_accelerated_files = 4000"        >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "opcache.revalidate_freq = 60"                >> /usr/local/etc/php/conf.d/custom.ini && \
    echo "opcache.fast_shutdown = 1"                   >> /usr/local/etc/php/conf.d/custom.ini

EXPOSE 80
