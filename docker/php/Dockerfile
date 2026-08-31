FROM php:8.5-fpm-alpine

ARG UID=1000
ARG GID=1000

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# install-php-extensions resolves the correct build deps for Alpine itself
# and removes them again after compiling, so the final image stays small.
RUN apk add --no-cache git unzip \
    && install-php-extensions \
        intl \
        zip \
        pdo_mysql \
        mbstring \
        xdebug

# Create an unprivileged user matching the default host uid/gid (1000:1000).
# This keeps container processes from running as root and makes file
# ownership on the bind mount consistent with the host user.
RUN addgroup -g ${GID} app \
    && adduser -u ${UID} -G app -s /bin/sh -D app

WORKDIR /var/www/html

# Configure the FPM pool to run as the unprivileged user.
COPY fpm/zz-user.conf /usr/local/etc/php-fpm.d/zz-user.conf

USER app

CMD ["php-fpm"]
