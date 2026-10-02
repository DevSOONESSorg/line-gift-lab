# 教材アプリ（Laravel）を動かすためのコンテナ
# PHP の版は CI（.github/actions/setup-app/action.yml の php-version）と必ずそろえる。
# 版を上げるときは、実物のアプリが上げたあとに、この2か所を同じ PR で変える
FROM php:8.5-cli-bookworm

# zip/unzip は composer が使う。sqlite は PHP 本体に入っている
RUN apt-get update && apt-get install -y --no-install-recommends unzip git libzip-dev \
 && docker-php-ext-install zip \
 && rm -rf /var/lib/apt/lists/*

# composer（PHP のパッケージ管理）
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
EXPOSE 3000
CMD ["sh", "docker/start.sh"]
