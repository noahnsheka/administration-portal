# Use PHP 8.2 with Apache
FROM php:8.2-apache

# Install only essential dependencies (PostgreSQL PDO)
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    libpq-dev \
    libzip-dev && \
    docker-php-ext-install -j$(nproc) pdo pdo_pgsql zip && \
    a2enmod rewrite && \
    a2enmod headers && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Create .htaccess for URL rewriting
RUN echo '<IfModule mod_rewrite.c>' > /var/www/html/.htaccess && \
    echo '    RewriteEngine On' >> /var/www/html/.htaccess && \
    echo '    RewriteBase /' >> /var/www/html/.htaccess && \
    echo '    RewriteCond %{REQUEST_FILENAME} !-f' >> /var/www/html/.htaccess && \
    echo '    RewriteCond %{REQUEST_FILENAME} !-d' >> /var/www/html/.htaccess && \
    echo '</IfModule>' >> /var/www/html/.htaccess

# Create startup script to handle dynamic PORT and ensure Render env vars reach PHP (required for Render)
RUN echo '#!/bin/bash' > /usr/local/bin/docker-entrypoint.sh && \
    echo 'set -e' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '# Default APP_FOLDER to empty for root-path deployment (Render, Docker)' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'if [ -z "${APP_FOLDER+x}" ]; then' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '  export APP_FOLDER=""' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'fi' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '# Write Render environment variables to a file PHP can read for DB connectivity' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'env | grep -E "^(DB_|APP_)" > /var/www/html/.env.render 2>/dev/null || true' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'chmod 644 /var/www/html/.env.render 2>/dev/null || true' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'if [ -n "$PORT" ]; then' >> /usr/local/bin/docker-entrypoint.sh && \
    echo '  sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'fi' >> /usr/local/bin/docker-entrypoint.sh && \
    echo 'exec apache2-foreground' >> /usr/local/bin/docker-entrypoint.sh && \
    chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose port (Render will override with PORT environment variable)
EXPOSE 80

# Start Apache with dynamic port handling
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

