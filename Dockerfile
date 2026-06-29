FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libonig-dev \
    libpng-dev \
    libicu-dev \
    libpq-dev \
    && docker-php-ext-install pdo_pgsql zip mbstring intl

RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && npm install -g npm

RUN npm install -g @anthropic-ai/claude-code

RUN apt-get install -y python3 python3-pip pipx \
    && PIPX_HOME=/usr/local/pipx PIPX_BIN_DIR=/usr/local/bin \
       pipx install git+https://github.com/github/spec-kit.git

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

