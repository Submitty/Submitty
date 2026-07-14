FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive

# =====================================================
# Install base requirements for testing
# =====================================================

# Install system utils
RUN apt-get update \
    && apt-get install -y --no-install-recommends software-properties-common \
    curl \
    git \
    gnupg2 \
    ca-certificates \
    unzip \
    poppler-utils \
    libzbar0 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install NodeJS
# Node 20 manually installed since it's not avb in default Ubuntu 22.04 packages
RUN apt-get update \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install PHP
# PHP 8.2 manually installed since it's not avb in default Ubuntu 22.04 packages
RUN add-apt-repository ppa:ondrej/php -y \
    && apt-get update \
    && apt-get install -y --no-install-recommends php8.2-cli \
    php8.2-xml \
    php8.2-mbstring \
    php8.2-curl \
    php8.2-zip \
    php8.2-ldap \
    php8.2-sqlite3 \
    php8.2-imagick \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Python
RUN apt-get update \
    && apt-get install -y --no-install-recommends python3 \
    python3-pip \
    python3-setuptools \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# =====================================================
# Set up container directory structure
# =====================================================

ENV HOME=/home/submitty
RUN mkdir -p $HOME/site && chmod 1777 $HOME

# /test_suite needed for python unit tests
RUN mkdir -p /test_suite && chmod 1777 /test_suite
WORKDIR $HOME/site

# =====================================================
# Install testing / linting dependencies
# =====================================================

# Python dependencies
COPY .setup/pip/dev_requirements.txt .setup/pip/system_requirements.txt $HOME/pip/
RUN pip3 install --no-cache-dir \
    -r $HOME/pip/dev_requirements.txt \
    -r $HOME/pip/system_requirements.txt

# Node & PHP dependencies
COPY site/composer.json site/composer.lock site/package.json site/package-lock.json ./
RUN composer install --no-scripts --no-interaction --prefer-dist \
    && npm ci \
    && npm cache clean --force \
    && rm -rf /root/.composer/cache ~/.npm \
    && chmod -R 777 .

# install requirements for python_submitty_utils
COPY python_submitty_utils/requirements.txt python_submitty_utils/setup.py $HOME/python_submitty_utils/
RUN pip3 install --no-cache-dir -r $HOME/python_submitty_utils/requirements.txt
RUN pip3 install --no-cache-dir -e $HOME/python_submitty_utils
