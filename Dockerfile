FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive

# Install dependencies, including Node 20 and PHP 8.2
# which are not available by default in Ubuntu 22.04
RUN apt-get update && apt-get install -y --no-install-recommends \
    software-properties-common curl git gnupg2 ca-certificates unzip \
    python3 python3-pip python3-setuptools poppler-utils libzbar0 \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && add-apt-repository ppa:ondrej/php -y \
    && apt-get update && apt-get install -y --no-install-recommends \
    php8.2-cli php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

WORKDIR /submitty/site

ENV HOME=/tmp
RUN chmod 1777 /tmp

# Install dependencies
COPY .setup/pip/dev_requirements.txt .setup/pip/system_requirements.txt /tmp/pip/
RUN pip3 install --no-cache-dir \
    -r /tmp/pip/dev_requirements.txt \
    -r /tmp/pip/system_requirements.txt \
    coverage flake8 pylint

COPY python_submitty_utils /submitty/python_submitty_utils
RUN pip3 install --no-cache-dir -e /submitty/python_submitty_utils

COPY site/composer.json site/composer.lock site/package.json site/package-lock.json ./
RUN composer install --no-scripts --no-interaction --prefer-dist \
    && npm ci \
    && chmod -R 777 /submitty/site
