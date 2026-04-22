ARG COMPOSER_VERSION=2.7.7
ARG PHP_VERSION=8.3

FROM composer/composer:${COMPOSER_VERSION} AS composer

FROM php:${PHP_VERSION}-cli-alpine AS base

ARG UID=1000
ARG USER=castor

# Create a development user
RUN adduser $USER --disabled-password --home /$USER --uid $UID

# Add easy extension install support
# Docs: https://github.com/mlocati/docker-php-extension-installer
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

# Download and install composer
COPY --from=composer /usr/bin/composer /usr/bin/composer

FROM base AS dev

RUN install-php-extensions xdebug pcntl posix pdo_pgsql pdo_sqlite decimal

# Install and Configure XDebug
COPY ./xdebug.ini /usr/local/etc/php/conf.d/60_xdebug.ini