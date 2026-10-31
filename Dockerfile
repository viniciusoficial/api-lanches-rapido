FROM php:8.2-cli

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# O contexto de build do Render deve ser api_php/.
# A pasta public contém o router PHP; src/ e data/ ficam ao lado dela.
COPY . /app
COPY php.ini /usr/local/etc/php/conf.d/zz-lancherapido.ini

# O disco persistente do Render deve ser montado aqui.
RUN mkdir -p /app/data

ENV PORT=10000
EXPOSE 10000

# index.php atua como router para /health, /products, /orders e demais rotas.
CMD ["sh", "-c", "exec php -S 0.0.0.0:${PORT:-10000} -t /app/public /app/public/index.php"]
