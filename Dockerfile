FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies (Node 20 & PHP 8.2)
RUN apt-get update && apt-get install -y --no-install-recommends \
        software-properties-common curl git gnupg2 ca-certificates unzip \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update && apt-get install -y --no-install-recommends \
        php8.2-cli php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /site

ENV HOME=/tmp
RUN chmod 1777 /tmp

# Install dependencies
COPY site/composer.json site/composer.lock site/package.json site/package-lock.json ./
RUN composer install --no-scripts --no-interaction --prefer-dist \
    && npm ci

# Copy the rest of the site directory
COPY site/ .

RUN chmod -R 777 /site
