FROM php:8.2-cli

WORKDIR /app

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# O contexto do Render deve ser api_php/.
COPY . /app
COPY docker-entrypoint.sh /usr/local/bin/lancherapido-entrypoint.sh

# O disco persistente do Render, se configurado, deve ser montado em /app/data.
RUN mkdir -p /app/data \
    && chmod +x /usr/local/bin/lancherapido-entrypoint.sh \
    && printf '%s\n' \
        '[PHP]' \
        'expose_php = Off' \
        'display_errors = Off' \
        'display_startup_errors = Off' \
        'log_errors = On' \
        'error_log = /proc/self/fd/2' \
        'default_charset = UTF-8' \
        'memory_limit = 128M' \
        'max_execution_time = 30' \
        'post_max_size = 8M' \
        > /usr/local/etc/php/conf.d/zz-lancherapido.ini

ENV PORT=10000
EXPOSE 10000

# Opcionalmente cria/atualiza a conta admin pelas variáveis seguras do serviço.
ENTRYPOINT ["/usr/local/bin/lancherapido-entrypoint.sh"]
