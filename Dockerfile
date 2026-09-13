FROM php:8.4-cli

# Install system dependencies and PostgreSQL development libraries
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    && docker-php-ext-install pdo pdo_pgsql pgsql zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install composer dependencies ignoring platform requirements constraints
RUN composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# Storage permissions
RUN chmod -R 777 storage bootstrap/cache

# Expose container port
EXPOSE 8000

# Start Laravel development server listening on Render $PORT
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
