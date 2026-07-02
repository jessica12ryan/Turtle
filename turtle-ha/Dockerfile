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

# Install Mailpit (multi-arch) — pinned to v1.30.2
ARG BUILD_ARCH
RUN MPVER="v1.30.2" \
    && case "${BUILD_ARCH}" in \
         amd64)   MPARCH="amd64" ;; \
         aarch64) MPARCH="arm64" ;; \
         armv7)   MPARCH="arm" ;; \
         *)       echo "Unsupported arch: ${BUILD_ARCH}"; exit 1 ;; \
       esac \
    && curl -fsL --retry 3 --retry-delay 5 "https://github.com/axllent/mailpit/releases/download/${MPVER}/mailpit-linux-${MPARCH}.tar.gz" -o /tmp/mailpit.tar.gz \
    && tar xzf /tmp/mailpit.tar.gz -C /usr/local/bin/ mailpit \
    && chmod +x /usr/local/bin/mailpit \
    && rm /tmp/mailpit.tar.gz

# php82 -> php alias for scripts that call `php`
RUN ln -sf /usr/bin/php82 /usr/bin/php

# Clone Turtle stable branch at build time; in-app updater handles future updates via git pull
RUN git clone --depth=1 --branch stable https://github.com/jessica12ryan/Turtle.git /var/www/turtle \
    && chmod -R a+w /var/www/turtle

# Apache: switch to port 8099, enable rewrite
RUN sed -i 's/^Listen 80$/Listen 8099/' /etc/apache2/httpd.conf \
    && sed -i 's/#LoadModule rewrite_module/LoadModule rewrite_module/' /etc/apache2/httpd.conf \
    && echo "ServerName localhost" >> /etc/apache2/httpd.conf

# PHP tweaks
RUN printf "upload_max_filesize=64M\npost_max_size=64M\nmemory_limit=256M\n" \
    > /etc/php82/conf.d/turtle.ini

COPY rootfs/etc/apache2/sites-available/turtle.conf /etc/apache2/conf.d/turtle.conf
COPY rootfs/ /

RUN chmod +x /usr/bin/turtle-run.sh \
    && chmod +x /etc/s6-overlay/s6-rc.d/turtle/run

EXPOSE 8099
