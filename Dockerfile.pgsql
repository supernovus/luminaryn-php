FROM luminaryn/php:7-core
RUN apt-get update && apt-get install -y libpq-dev \
    && apt-get clean \
    && docker-php-ext-install -j$(nproc) pgsql pdo_pgsql
