FROM ubuntu:22.04
ENV DEBIAN_FRONTEND=noninteractive

# =====================================================
# Install everything necessary for testing
RUN apt-get update

# Install system utils
RUN apt-get install -y \
        software-properties-common curl git gnupg2 ca-certificates unzip \
        poppler-utils \
        libzbar0


# Install NodeJS
# Node 20 manually installed since it's not avb in default Ubuntu 22.04 packages
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install PHP
# PHP 8.2 manually installed since it's not avb in default Ubuntu 22.04 packages
RUN add-apt-repository ppa:ondrej/php -y \
    && apt-get update \
    && apt-get install -y \
        php8.2-cli php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip

# Install Python
RUN apt-get install -y \
        python3 \
        python3-pip \
        python3-setuptools

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Clean up package lists
RUN rm -rf /var/lib/apt/lists/*

# =====================================================

# =====================================================
# Set up container directory structure
ENV HOME=/home/submitty
RUN mkdir -p $HOME/site && chmod 1777 $HOME

# /test_suite needed for python unit tests
RUN mkdir -p /test_suite && chmod 1777 /test_suite
WORKDIR $HOME/site
# =====================================================

# Install testing / linting dependencies
COPY .setup/pip/dev_requirements.txt .setup/pip/system_requirements.txt $HOME/pip/
RUN pip3 install --no-cache-dir \
    -r $HOME/pip/dev_requirements.txt \
    -r $HOME/pip/system_requirements.txt \
    coverage

COPY python_submitty_utils $HOME/python_submitty_utils
RUN pip3 install --no-cache-dir -e $HOME/python_submitty_utils

COPY site/composer.json site/composer.lock site/package.json site/package-lock.json ./
RUN composer install --no-scripts --no-interaction --prefer-dist \
    && npm ci \
    && chmod -R 777 .
