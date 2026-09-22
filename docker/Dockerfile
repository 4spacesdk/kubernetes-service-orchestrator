# Node for the Vue app. One stage for both uses: the production build below, and the
# `vue` service in docker-compose.yml, where vite runs in development. So the Node that
# builds what ships is the Node a developer runs, and neither depends on the machine.
FROM node:16.13.1-alpine AS vue-toolchain
WORKDIR /vue

# Build Vue app
FROM vue-toolchain AS build-stage
COPY vue/package*.json ./
RUN npm install
COPY vue /vue
RUN node --version
RUN npm --version

ARG BUILD_TAG_NAME=latest
RUN sed -i -e "s/_VERSION_/$BUILD_TAG_NAME/g" src/versions.ts
ARG PROJECT_ID=project
RUN sed -i -e "s/_PROJECT_ID_/$PROJECT_ID/g" src/versions.ts

RUN npm run build


# PHP DeploymentSpecs can be found at https://hub.docker.com/_/php/
FROM php:8.5.10-alpine3.24

# Install OS dependencies
RUN apk add --update  \
    apache2 \
    zlib-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    icu-dev \
    gmp-dev \
    imagemagick \
    imagemagick-dev \
    libwebp-dev \
    libxml2-dev \
    libzip-dev \
    unzip \
    nano \
    zip \
    libxml2-dev \
    wget \
    git \
    curl-dev \
    yaml-dev \
    linux-headers \
    php85-apache2 php85-mbstring php85-session php85-json php85-tokenizer php85-mysqli php85-pdo php85-pdo_mysql php85-intl \
    php85-curl php85-pecl-yaml php85-calendar php85-posix php85-zip php85-xml php85-simplexml php85-xmlwriter php85-ctype \
    php85-pecl-imagick php85-gd php85-bcmath php85-gmp \
    # Alpine
    autoconf \
    gcc make g++ \
    gcompat \
    # gcloud installer
    python3 \
    # runuser
    runuser \
    # gke-auth
    go

RUN docker-php-ext-configure intl
RUN docker-php-ext-install -j$(nproc) mysqli pdo pdo_mysql intl zip pcntl soap sockets calendar posix ctype gmp
RUN docker-php-ext-enable pdo_mysql intl posix
RUN docker-php-ext-configure calendar

# GD
RUN docker-php-ext-configure gd \
      --enable-gd \
      --with-webp \
      --with-freetype=/usr/include/ \
      --with-jpeg=/usr/include/
RUN docker-php-ext-install -j$(nproc) bcmath gd
RUN docker-php-ext-enable gd
RUN cp /usr/local/etc/php/conf.d/docker-php-ext-gd.ini /etc/php85/conf.d/gd.ini

# SQLSRV (MSSQL Driver)
#
# Microsoft publishes these packages for x86_64 only - there is no arm64 Alpine build of
# msodbcsql18. An image built on arm64 therefore has no MSSQL driver and cannot provision
# a database service whose driver is mssql. Everything else is identical, and the images
# that ship are built for amd64.
#
# Without this the whole build fails on Apple Silicon the moment the layer is not cached,
# which is what used to happen.
#
# The packages are not signed with an Alpine key, so apk has to be told to take them
# (--allow-untrusted). What stands in for the signature is the checksum: fetched over TLS with
# the certificate checked, and compared with the sha256 of the files as Microsoft signed them -
# verified against Microsoft's release key when they were pinned. A new version needs new sums.
ARG TARGETARCH
RUN if [ "$TARGETARCH" = "amd64" ] || [ -z "$TARGETARCH" ]; then \
    curl -fsSLO https://download.microsoft.com/download/8/6/8/868e5fc4-7bfe-494d-8f9d-115cbcdb52ae/msodbcsql18_18.1.2.1-1_amd64.apk && \
    curl -fsSLO https://download.microsoft.com/download/8/6/8/868e5fc4-7bfe-494d-8f9d-115cbcdb52ae/mssql-tools18_18.1.1.1-1_amd64.apk && \
    echo "6845b54e693a1896aec4a188aa28de14a99297aeacb00da88953e0526b01f3af  msodbcsql18_18.1.2.1-1_amd64.apk" | sha256sum -c - && \
    echo "a12b9f841c6c88b5d6a1cc8f04a63ade6efaa3ebae440887c79ab6556fee4c5b  mssql-tools18_18.1.1.1-1_amd64.apk" | sha256sum -c - && \
    apk add --allow-untrusted msodbcsql18_18.1.2.1-1_amd64.apk && \
    apk add --allow-untrusted mssql-tools18_18.1.1.1-1_amd64.apk && \
    apk add --no-cache --virtual .persistent-deps freetds && \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS unixodbc-dev freetds-dev && \
    docker-php-source extract && \
    docker-php-ext-install pdo_dblib && \
    pecl install sqlsrv pdo_sqlsrv && \
    docker-php-ext-enable pdo_sqlsrv && \
    docker-php-ext-enable --ini-name 30-sqlsrv.ini sqlsrv && \
    docker-php-ext-enable --ini-name 35-pdo_sqlsrv.ini pdo_sqlsrv && \
    apk del .build-deps && \
    rm msodbcsql18_18.1.2.1-1_amd64.apk && \
    rm mssql-tools18_18.1.1.1-1_amd64.apk && \
    cp /usr/local/etc/php/conf.d/30-sqlsrv.ini /etc/php85/conf.d/sqlsrv.ini && \
    cp /usr/local/etc/php/conf.d/docker-php-ext-pdo_sqlsrv.ini /etc/php85/conf.d/pdo_sqlsrv.ini && \
    cp /usr/local/lib/php/extensions/no-debug-non-zts-20250925/sqlsrv.so /usr/lib/php85/modules/sqlsrv.so && \
    cp /usr/local/lib/php/extensions/no-debug-non-zts-20250925/pdo_sqlsrv.so /usr/lib/php85/modules/pdo_sqlsrv.so ; \
  else \
    echo "Skipping the MSSQL driver: Microsoft ships no arm64 Alpine package." ; \
  fi

# yaml_parse
RUN pear config-set php_ini /etc/php7/php.ini
RUN pecl channel-update pecl.php.net
# 2.3.0 rather than 2.2.2: the older release includes ext/standard/php_smart_string.h,
# which PHP 8.5 no longer ships.
RUN pecl install yaml-2.3.0 && docker-php-ext-enable yaml

# Code coverage for the test suites.
#
# PCOV rather than Xdebug: it only measures coverage, and it is an order of magnitude
# faster at it. Xdebug would also give step debugging in PhpStorm, which is worth having -
# but that is a separate decision and a separate extension to install, not a reason to put
# a debugger in the image that ships.
#
# Switched off in the ini, so it costs nothing on a production request. The test scripts
# turn it on per run with `-d pcov.enabled=1`. `pcov.directory` keeps it from instrumenting
# vendor code, which is most of what makes it fast.
RUN pecl install pcov && docker-php-ext-enable pcov
RUN { \
      echo "pcov.enabled=0"; \
      echo "pcov.directory=/var/www/html/ci4/app"; \
    } > /usr/local/etc/php/conf.d/docker-php-ext-pcov-defaults.ini

# Install gke-auth. A version, not @latest: go checks what it fetches against sum.golang.org,
# so a pinned version is the same code on every build.
ARG GKE_AUTH_ENABLED=false
RUN if [[ "$GKE_AUTH_ENABLED" == "TRUE" ]] ; then GOBIN=/usr/local/bin go install github.com/imjasonh/gke-auth@v0.3.3 ; fi

# Composer, copied from its official image like Trivy below, instead of running whatever the
# installer script at getcomposer.org says that day. Pinned - bump it on purpose.
COPY --from=composer:2.10.3 /usr/bin/composer /usr/bin/composer

# Trivy, which scans the customers' images for known vulnerabilities (app:scan-container-images).
# Copied from the official image: it is built for both architectures, so this picks the one
# being built. Pinned - bump it on purpose. About 42 MB to pull, 156 MB on disk.
COPY --from=aquasec/trivy:0.74.0 /usr/local/bin/trivy /usr/local/bin/trivy

RUN rm -rf /tmp/* /var/cache/apk/*

# `X-Powered-By: PHP/8.5.10` on every response, which is a version number and an invitation
# to look up what is wrong with it. Written for both PHPs in the image: the Alpine one under
# /etc/php85 is what Apache runs, and the one under /usr/local is the CLI.
RUN { \
      echo "expose_php=Off"; \
    } | tee /etc/php85/conf.d/99_kso-hardening.ini > /usr/local/etc/php/conf.d/99-kso-hardening.ini

# Configure apache
COPY docker/apache/httpd.conf /etc/apache2/httpd.conf


ENV APP_HOME /var/www/html
# Read by the Content-Security-Policy in docker/apache/httpd.conf; the chart sets the real one.
ENV CENTRIFUGO_EXTERNAL_URL=""
RUN mkdir -p $APP_HOME && chown www-data:www-data /var/www $APP_HOME
# Apache's document root: empty, see docker/apache/httpd.conf.
RUN mkdir -p /var/www/docroot

# Composer dependencies before the application files, so a change to the code reuses the
# cached vendor layers - they are only reinstalled when a composer.lock changes.
# ext-sqlsrv is required in composer.json but absent on arm64, see the SQLSRV layer above.
COPY --chown=www-data ci4/composer.json ci4/composer.lock $APP_HOME/ci4/
RUN cd $APP_HOME/ci4 && \
  if [ "$TARGETARCH" = "amd64" ] || [ -z "$TARGETARCH" ]; then composer install; \
  else composer install --ignore-platform-req=ext-sqlsrv; fi

# Copy application files
COPY --chown=www-data ci4 $APP_HOME/ci4
COPY --chown=www-data --from=build-stage /vue/dist $APP_HOME/vue

# CodeIgniter opens its file cache on every boot, spark included, and stops if the
# directory is missing. Its contents are gitignored, so do not rely on the checkout.
RUN mkdir -p $APP_HOME/ci4/writable/cache && chown www-data:www-data $APP_HOME/ci4/writable/cache

# The directory itself too, whatever COPY made of it: the test suites run in this image, as
# www-data, and PHPUnit writes its cache there and its logs to build/.
RUN mkdir -p $APP_HOME/ci4/build && chown www-data:www-data $APP_HOME/ci4 $APP_HOME/ci4/build

# Set TAG_NAME as Environment Variable
ARG BUILD_TAG_NAME=latest
ENV TAG_NAME ${BUILD_TAG_NAME}

## Git Commit
ARG BUILD_SHORT_SHA
ENV SHORT_SHA ${BUILD_SHORT_SHA}

# Starts the job queue's worker beside Apache.
COPY docker/entrypoint-wrapper /usr/local/bin/entrypoint-wrapper
RUN chmod 775 /usr/local/bin/entrypoint-wrapper

# Not root. What the processes write at runtime is theirs: Apache's pid and lock files, and
# Trivy's databases where the chart does not mount a volume over them. Everything else is read,
# or written under ci4/writable and /tmp. Numeric, so Kubernetes can tell it is not root
# (runAsNonRoot) without looking the name up.
RUN mkdir -p /run/apache2 /var/cache/kso-trivy \
 && chown www-data:www-data /run/apache2 /var/cache/kso-trivy
USER 82:82

ENTRYPOINT [ "entrypoint-wrapper" ]

