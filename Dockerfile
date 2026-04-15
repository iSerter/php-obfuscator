# Stage 1: Install dependencies
FROM composer:latest AS composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# Stage 2: Final image
FROM php:8.4-cli-alpine

LABEL maintainer="iSerter <iserter@users.noreply.github.com>"
LABEL org.opencontainers.image.description="A modern PHP obfuscator for protecting intellectual property."

WORKDIR /app

# Install necessary system dependencies for PHP
RUN apk add --no-cache \
    libzip-dev \
    zip \
    unzip

# Copy the application from the build stage
COPY . .
COPY --from=composer /app/vendor ./vendor

# Generate optimized autoloader
COPY --from=composer /usr/bin/composer /usr/bin/composer
RUN composer dump-autoload --optimize --no-dev

# Entrypoint filters empty args from GitHub Actions
RUN chmod +x /app/entrypoint.sh
ENTRYPOINT ["/app/entrypoint.sh"]
CMD ["--help"]
