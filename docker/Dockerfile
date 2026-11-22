# syntax=docker/dockerfile:1.7
# Verso image for trying the CMS locally: PHP 8.4, the code with vendor and the built
# assets, served by "php artisan serve". Build it from the repository root:
#   docker build -f docker/Dockerfile -t verso:local .

ARG PHP_IMAGE=php:8.4-cli-bookworm
ARG NODE_IMAGE=node:22-bookworm-slim
ARG COMPOSER_IMAGE=composer:2.11
ARG EXTENSION_INSTALLER_IMAGE=mlocati/php-extension-installer:2.13.1

FROM ${COMPOSER_IMAGE} AS composer
FROM ${NODE_IMAGE} AS node
FROM ${EXTENSION_INSTALLER_IMAGE} AS extension-installer

# 1. Composer dependencies. PHP code does not depend on the CPU, so this runs on the build machine.
FROM --platform=$BUILDPLATFORM ${PHP_IMAGE} AS vendor
SHELL ["/bin/bash", "-o", "pipefail", "-c"]
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/bin/composer
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_NO_INTERACTION=1
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress \
        --ignore-platform-req='ext-*'
COPY . .
# A throwaway environment so artisan can boot during the build, like scripts/build-release.sh.
RUN cp .env.example .env \
    && touch database/database.sqlite \
    && composer dump-autoload --no-dev --optimize \
    && php artisan key:generate --force --quiet

# 2. Admin and theme assets. Wayfinder runs artisan during the build, so this stage has PHP too.
FROM vendor AS assets
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && npm ci --no-audit --no-fund \
    && npm run build \
    && rm -rf node_modules tests resources/js/tests .env .env.backup .env.production \
        database/*.sqlite* public/hot public/storage .phpunit.cache dist docker site \
    && find storage -type f ! -name '.gitignore' -delete \
    && head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n' > .verso-image-build

# 3. Runtime: PHP with the extensions Verso needs and the finished code in /opt/verso.
FROM ${PHP_IMAGE} AS runtime
ARG VERSO_VERSION=dev
ARG VERSO_REVISION=unknown
LABEL org.opencontainers.image.title="Verso" \
      org.opencontainers.image.description="Verso, an open source CMS on Laravel, ready to try locally." \
      org.opencontainers.image.source="https://github.com/victoragudo/verso" \
      org.opencontainers.image.url="https://verso-cms.pages.dev" \
      org.opencontainers.image.documentation="https://github.com/victoragudo/verso/blob/main/docs/DOCKER.md" \
      org.opencontainers.image.licenses="MIT" \
      org.opencontainers.image.version="${VERSO_VERSION}" \
      org.opencontainers.image.revision="${VERSO_REVISION}"

COPY --from=extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions bcmath exif gd intl pdo_mysql pdo_pgsql zip \
    && apt-get update \
    && apt-get install -y --no-install-recommends rsync \
    && rm -rf /var/lib/apt/lists/* /usr/local/bin/install-php-extensions

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-verso.ini
COPY docker/entrypoint.sh /usr/local/bin/verso-entrypoint

RUN groupadd --gid 1000 verso \
    && useradd --uid 1000 --gid verso --home-dir /var/www --no-create-home --shell /usr/sbin/nologin verso \
    && mkdir -p /var/www/verso \
    && chown verso:verso /var/www/verso \
    && chmod 0755 /usr/local/bin/verso-entrypoint

COPY --from=assets /app /opt/verso

USER verso
WORKDIR /var/www/verso
ENV VERSO_PORT=8000
EXPOSE 8000
ENTRYPOINT ["verso-entrypoint"]
