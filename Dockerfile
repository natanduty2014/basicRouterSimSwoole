FROM phpswoole/swoole:6.0-php8.4

# copy entrypoint to container to /home
COPY ./docker/entrypoint.sh /home/entrypoint.sh

# make entrypoint executable
RUN chmod +x /home/entrypoint.sh

WORKDIR /public

RUN apt-get update && apt-get install vim -y && \
    #apt-get install -y fswatch && \
    apt-get install openssl -y && \
    apt-get install libssl-dev -y && \
    apt-get install wget -y && \
    apt-get install git -y && \
    apt-get install procps -y && \
    apt-get install htop -y && \
    apt-get install redis -y && \
    apt-get install python3-pip -y && \
    apt-get install python3-venv -y

COPY docker/requirements.txt /tmp/requirements.txt
RUN python3 -m venv /opt/venv \
    && /opt/venv/bin/pip install --upgrade pip \
    && /opt/venv/bin/pip install -r /tmp/requirements.txt
ENV PATH="/opt/venv/bin:$PATH"

RUN set -ex \
    && apt update && apt upgrade --yes \
    && apt install --yes libzip-dev \
    # && docker-php-ext-install -j$(nproc) opcache pdo_mysql zip \
    && pecl update-channels \
    && pecl install inotify \
    && docker-php-ext-enable inotify \
    && apt clean && rm -rf /var/lib/apt/lists && rm -rf /tmp/pear


# RUN pecl install -o -f redis \
# &&  rm -rf /tmp/pear \
# &&  docker-php-ext-enable redis

RUN docker-php-ext-install pdo_mysql

# RUN pecl install runkit7-4.0.0a6 \
#     && docker-php-ext-enable runkit7

# RUN pecl install xdebug-3.2.1 \
#     && docker-php-ext-enable xdebug

# Configuração adicional para a extensão GD com suporte a JPEG, PNG e WebP
RUN apt-get update && \
    apt-get install -y libjpeg62-turbo-dev libpng-dev libfreetype6-dev libwebp-dev && \
    docker-php-ext-configure gd --with-jpeg=/usr/include/ --with-freetype=/usr/include/ --with-webp=/usr/include/ && \
    docker-php-ext-install -j$(nproc) gd

RUN apt install -y curl

#gzip
RUN apt-get install -y libz-dev && \
    docker-php-ext-install zip

RUN apt-get install libsodium-dev -y
RUN docker-php-ext-install sodium
#RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - &&\
#apt-get install -y nodejs

# Configurar o fuso horário
RUN ln -sf /usr/share/zoneinfo/America/Fortaleza /etc/localtime

#enable ffi
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends libffi-dev; \
    rm -rf /var/lib/apt/lists/*; \
    docker-php-ext-install ffi

# Instala as dependências necessárias para compilar o GMP
RUN apt-get update && apt-get install -y \
    libgmp-dev \
    && rm -rf /var/lib/apt/lists/*

# Instala a extensão GMP via script interno do Docker
RUN docker-php-ext-install gmp

# (Opcional) Se precisar ativar manualmente via ini, faça:
# RUN docker-php-ext-enable gmp

# netcat
# RUN apt-get update && apt-get install netcat -y
#memory limit
RUN echo "memory_limit = 256M" > /usr/local/etc/php/conf.d/memory-limit.ini

RUN git config --global --add safe.directory /public

RUN composer self-update --2



RUN mkdir -p /var/log/supervisor

ENTRYPOINT [ "/home/entrypoint.sh" ]

