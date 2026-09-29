# 教材アプリ（Laravel）を動かすためのコンテナ
FROM php:8.3-cli-bookworm

# zip/unzip は composer が使う。sqlite は PHP 本体に入っている
RUN apt-get update && apt-get install -y --no-install-recommends unzip git libzip-dev \
 && docker-php-ext-install zip \
 && rm -rf /var/lib/apt/lists/*

# composer（PHP のパッケージ管理）
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
EXPOSE 3000
CMD ["sh", "docker/start.sh"]
