FROM php:8.3-cli-bookworm

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libcurl4-openssl-dev \
        libonig-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" curl mbstring pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /workspace/server
EXPOSE 8000
CMD ["php", "think", "run", "--host=0.0.0.0", "--port=8000"]
