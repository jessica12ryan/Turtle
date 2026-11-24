# Digests resolved 2026-11-24; update deliberately via Dependabot.
FROM composer:2@sha256:cd3a90dc5624c59a2f9ddf80dad08569f06416db31de519024f249d63bca554b AS composer

FROM php:8.5-apache@sha256:fcf1877806085ea8ceec1072f93c1fed4df701f5da21ff5c976c3c362e4b7859

# Composer is used to install the dev dependencies (PHPUnit) when running the
# test suites inside this image. Keep it in the image so local, AI and CI runs
# all use the exact same PHP version and extensions as the app.
COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    libicu-dev \
    libheif-examples \
    libcurl4-openssl-dev \
    unzip \
    curl \
    git \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && for ext in pdo_mysql mbstring xml bcmath gd zip curl intl; do \
        docker-php-ext-install "$ext" \
        || { echo "Failed to install $ext" >&2; exit 1; }; \
    done \
    && pecl install redis-6.3.0 \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY php.ini /usr/local/etc/php/conf.d/turtle.ini

RUN a2enmod rewrite headers

ENV APACHE_DOCUMENT_ROOT=/var/www/html/www

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# The php:apache image symlinks its logs to /dev/stdout and /dev/stderr, so
# Settings -> Logging can never read them. Point the vhost at real files in the
# bind-mounted repo instead (done after the rewrites above so this path stays
# intact). stdout still receives other Apache output.
RUN sed -ri \
        -e 's!^([[:space:]]*)ErrorLog[[:space:]].*!\1ErrorLog /var/www/html/storage/logs/apache_error.log!' \
        -e 's!^([[:space:]]*)CustomLog[[:space:]].*!\1CustomLog /var/www/html/storage/logs/apache_access.log combined!' \
        /etc/apache2/sites-available/*.conf

COPY start.sh /usr/local/bin/turtle-start.sh
RUN chmod +x /usr/local/bin/turtle-start.sh

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["turtle-start.sh"]
