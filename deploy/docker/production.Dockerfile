# syntax=docker/dockerfile:1.7

FROM node:20.19.4-bookworm-slim AS admin-builder

WORKDIR /build/web
RUN corepack enable && corepack prepare pnpm@9.15.6 --activate
COPY web/package.json web/pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile
COPY plugins.lock /build/plugins.lock
COPY web/ ./
RUN pnpm exec vue-tsc --noEmit \
    && VITE_DEPLOYMENT_MODE=standalone pnpm exec vite build --config ./config/vite.config.prod.ts --outDir dist/standalone \
    && VITE_DEPLOYMENT_MODE=multi-tenant pnpm exec vite build --config ./config/vite.config.prod.ts --outDir dist/multi-tenant

FROM node:20.19.4-bookworm-slim AS mobile-builder

WORKDIR /build/uniapp
COPY uniapp/package.json uniapp/package-lock.json ./
RUN npm ci --legacy-peer-deps
COPY uniapp/ ./
ENV VITE_APP_BASE_URL=""
RUN npm run build:h5

FROM node:20.19.4-bookworm-slim AS platform-builder

WORKDIR /build/platform
COPY platform/package.json platform/package-lock.json ./
RUN npm ci
COPY platform/ ./
RUN npm run build

FROM node:20.19.4-bookworm-slim AS pc-builder

WORKDIR /build/pc
COPY pc/package.json pc/package-lock.json ./
RUN npm ci
COPY pc/ ./
RUN npm run generate

FROM composer:2.8 AS composer-deps

WORKDIR /build/server
COPY server/composer.json server/composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --no-scripts

FROM php:8.3-fpm-bookworm AS php

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-install -j"$(nproc)" curl mbstring pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/peanut-admin

COPY deploy/docker/php-upload.ini /usr/local/etc/php/conf.d/peanut-upload.ini

COPY LICENSE NOTICE THIRD_PARTY_NOTICES.md RELEASE_SBOM.spdx.json CHANGELOG.md RELEASE_METADATA.json legal/
COPY resources/project-resources.json resources/project-resources.json
COPY server/app server/app
COPY server/config server/config
COPY server/database server/database
COPY server/extend server/extend
COPY server/route server/route
COPY server/view server/view
COPY server/think server/think
COPY server/composer.json server/composer.lock server/
COPY server/public server/public
COPY --from=composer-deps /build/server/vendor server/vendor
COPY deploy/docker/php-entrypoint.sh /usr/local/bin/peanut-php-entrypoint

RUN mkdir -p server/runtime server/public/storage \
    && cd server \
    && php think service:discover \
    && php think vendor:publish \
    && cd .. \
    && chmod +x server/think server/database/seed-demo-data.php /usr/local/bin/peanut-php-entrypoint \
    && ln -s /var/www/peanut-admin/server/database/seed-demo-data.php /usr/local/bin/peanut-seed-demo-data \
    && chown -R www-data:www-data server/runtime server/public/storage

EXPOSE 9000

FROM nginx:1.28.0-alpine AS nginx

COPY deploy/nginx/peanut-admin.conf /etc/nginx/conf.d/default.conf
COPY deploy/docker/nginx-select-admin.sh /docker-entrypoint.d/40-select-admin.sh
COPY server/public /var/www/peanut-admin/server/public
COPY LICENSE NOTICE THIRD_PARTY_NOTICES.md RELEASE_SBOM.spdx.json CHANGELOG.md RELEASE_METADATA.json /var/www/peanut-admin/server/public/legal/
COPY --from=admin-builder /build/web/dist /opt/peanut-admin/admin
COPY --from=platform-builder /build/platform/dist /var/www/peanut-admin/server/public/platform
COPY --from=mobile-builder /build/uniapp/dist/build/h5 /var/www/peanut-admin/server/public/mobile
COPY --from=pc-builder /build/pc/.output/public /var/www/peanut-admin/server/public/pc

RUN chmod +x /docker-entrypoint.d/40-select-admin.sh \
    && mkdir -p /var/www/peanut-admin/server/public/storage
