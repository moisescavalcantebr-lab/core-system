FROM php:8.3.36-apache-trixie@sha256:756c2884677bedea30afaa95811b42cd3ff251203291c5820c1ad209e86c9fb4

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        unzip \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install pdo pdo_mysql mysqli gd \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod rewrite ssl

COPY mail-center.conf /etc/apache2/conf-available/mail-center.conf
RUN sed -i 's@CustomLog ${APACHE_LOG_DIR}/access.log combined@CustomLog ${APACHE_LOG_DIR}/access.log combined env=!mail_oauth_callback\n    CustomLog ${APACHE_LOG_DIR}/access.log mail_callback_safe env=mail_oauth_callback@' /etc/apache2/sites-available/000-default.conf \
    && a2enconf mail-center

RUN echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/custom.conf \
&& a2enconf custom

WORKDIR /var/www/html

# Fail new builds if the runtime lacks the security fix or required capabilities.
COPY verify-runtime.php /usr/local/lib/core-verify-runtime.php
RUN php /usr/local/lib/core-verify-runtime.php && apache2ctl -t
