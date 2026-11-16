FROM  --platform=linux/amd64 php:8.5.0RC3-cli-alpine

# Instalar dependencias necesarias
RUN apk add --no-cache $PHPIZE_DEPS \
    libxml2-dev \
    php-soap linux-headers bash \
    git curl tar zip

# Descargar y descomprimir Node.js 16 para Alpine
RUN NODE_VERSION=16.20.0 \
    && curl -fsSL https://unofficial-builds.nodejs.org/download/release/v$NODE_VERSION/node-v$NODE_VERSION-linux-x64-musl.tar.xz -o node-v$NODE_VERSION-linux-x64-musl.tar.xz \
    && tar -xf node-v$NODE_VERSION-linux-x64-musl.tar.xz \
    && mv node-v$NODE_VERSION-linux-x64-musl /usr/local/nodejs \
    && ln -s /usr/local/nodejs/bin/node /usr/local/bin/node \
    && ln -s /usr/local/nodejs/bin/npm /usr/local/bin/npm \
    && ln -s /usr/local/nodejs/bin/npx /usr/local/bin/npx \
    && rm node-v$NODE_VERSION-linux-x64-musl.tar.xz

# Copiar Composer desde una imagen oficial
COPY --from=composer:2.5.1 /usr/bin/composer /usr/bin/composer

# Instalar PHPUnit globalmente
RUN composer global require phpunit/phpunit ~9

# Crear alias para phpunit
RUN echo 'alias phpunit="./vendor/bin/phpunit"' >> ~/.bashrc
