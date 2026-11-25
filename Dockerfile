# syntax=docker/dockerfile:1
# Alvos: build (composer, extensões e ferramentas de teste; usado pelo serviço `test`) e prod (imagem final enxuta).

FROM php:8.4-cli-alpine AS base
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_pgsql intl
WORKDIR /app

FROM base AS build
RUN install-php-extensions pcov
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
ENV COMPOSER_HOME=/tmp/composer
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize

FROM build AS vendor-prod
RUN composer install --no-dev --no-interaction --no-progress --classmap-authoritative

FROM base AS prod
RUN addgroup -g 1000 -S app && adduser -u 1000 -S -G app app
COPY --from=vendor-prod /app/vendor ./vendor
COPY composer.json migrations.php migrations-db.php ./
COPY bin ./bin
COPY migrations ./migrations
COPY public ./public
COPY src ./src
USER app
EXPOSE 8080
# Servidor embutido do PHP com vários workers: suficiente para o estudo (ver "Desafios encontrados" no README).
ENV PHP_CLI_SERVER_WORKERS=4
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]
