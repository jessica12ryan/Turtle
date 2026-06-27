ARG BUILD_FROM
FROM ${BUILD_FROM}

# System deps: MariaDB, Apache, PHP 8.2 + extensions
RUN apk add --no-cache \
    apache2 \
    apache2-ctl \
    apache2-utils \
    mariadb \
    mariadb-client \
    mariadb-server-utils \
    php82 \
    php82-apache2 \
    php82-pdo \
    php82-pdo_mysql \
    php82-mysqlnd \
    php82-mbstring \
    php82-xml \
    php82-simplexml \
    php82-bcmath \
    php82-gd \
    php82-zip \
    php82-opcache \
    php82-curl \
    php82-intl \
    php82-session \
    php82-json \
    php82-tokenizer \
    php82-fileinfo \
    php82-ctype \
    php82-openssl \
    php82-dom \
    php82-xmlwriter \
    git \
    curl \
    bash

# php82 -> php alias for scripts that call `php`
RUN ln -sf /usr/bin/php82 /usr/bin/php

# Clone Turtle master branch — rebuilt on every push to master
RUN git clone --depth=1 --branch master https://github.com/jessica12ryan/Turtle.git /var/www/turtle

# Apache: switch to port 8099, enable rewrite
RUN sed -i 's/^Listen 80$/Listen 8099/' /etc/apache2/httpd.conf \
    && sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf \
    && echo "ServerName localhost" >> /etc/apache2/httpd.conf

# PHP tweaks
RUN printf "upload_max_filesize=64M\npost_max_size=64M\nmemory_limit=256M\n" \
    > /etc/php82/conf.d/turtle.ini

COPY rootfs/ /

RUN chmod +x /usr/bin/turtle-run.sh \
    && chmod +x /etc/s6-overlay/s6-rc.d/turtle/run

EXPOSE 8099
