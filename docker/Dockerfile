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
    php85-pecl-imagick php85-gd php85-bcmath \
    # Alpine
    autoconf \
    gcc make g++ \
    gcompat \
    # gcloud installer
    python3 \
    # ZMQ
    libzmq zeromq-dev zeromq \
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
ARG TARGETARCH
RUN if [ "$TARGETARCH" = "amd64" ] || [ -z "$TARGETARCH" ]; then \
    wget --no-check-certificate https://download.microsoft.com/download/8/6/8/868e5fc4-7bfe-494d-8f9d-115cbcdb52ae/msodbcsql18_18.1.2.1-1_amd64.apk && \
    wget --no-check-certificate https://download.microsoft.com/download/8/6/8/868e5fc4-7bfe-494d-8f9d-115cbcdb52ae/mssql-tools18_18.1.1.1-1_amd64.apk && \
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

# ZMQ Server
RUN wget https://github.com/zeromq/zeromq4-1/releases/download/v4.1.8/zeromq-4.1.8.tar.gz \
     && tar -xvzf zeromq-4.1.8.tar.gz \
     && cd zeromq-4.1.8 \
     && ./configure \
     && make -j$(nproc) \
     && make install \
     && ldconfig / \
     && cd .. \
     && rm -fr zeromq-4.1.4.tar.gz zeromq-4.1.8/

# ZMQ PHP Extension
#
# Built from remicollet's fork rather than zeromq/php-zmq, because upstream does not
# compile on PHP 8.5: zend_exception_get_default was removed, and XtOffsetOf no longer
# resolves. The fix is zeromq/php-zmq#240, four files and sixteen lines, open since
# July 2025 and unmerged - upstream's last commit is from January 2025 and it has an
# open "Project Status" issue.
#
# Pinned to a commit so a rebuild produces the same extension. Move back to upstream
# when the pull request lands.
RUN git clone https://github.com/remicollet/php-zmq.git \
 && cd php-zmq \
 && git checkout 4f7780d8 \
 && phpize && ./configure \
 && make \
 && make install \
 && cd .. \
 && rm -fr php-zmq
RUN docker-php-ext-enable zmq
RUN cp /usr/local/etc/php/conf.d/docker-php-ext-zmq.ini /etc/php85/conf.d/50_zmq.ini
RUN cp /usr/local/lib/php/extensions/no-debug-non-zts-20250925/zmq.so /usr/lib/php85/modules/zmq.so

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

# Install gke-auth
ARG GKE_AUTH_ENABLED=false
RUN if [[ "$GKE_AUTH_ENABLED" == "TRUE" ]] ; then GOBIN=/usr/local/bin go install github.com/imjasonh/gke-auth@latest ; fi

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

RUN rm -rf /tmp/* /var/cache/apk/*

# Configure apache
COPY docker/apache/httpd.conf /etc/apache2/httpd.conf

# Forward request and error logs to docker log collector
RUN ln -sf /dev/stdout /var/log/apache2/access.log \
    && ln -sf /dev/stderr /var/log/apache2/error.log

ENV APP_HOME /var/www/html
RUN mkdir -p $APP_HOME && chown www-data:www-data /var/www $APP_HOME

# Composer dependencies before the application files, so a change to the code reuses the
# cached vendor layers - they are only reinstalled when a composer.lock changes.
# ext-sqlsrv is required in composer.json but absent on arm64, see the SQLSRV layer above.
COPY --chown=www-data ci4/composer.json ci4/composer.lock $APP_HOME/ci4/
RUN cd $APP_HOME/ci4 && \
  if [ "$TARGETARCH" = "amd64" ] || [ -z "$TARGETARCH" ]; then composer install; \
  else composer install --ignore-platform-req=ext-sqlsrv; fi
COPY --chown=www-data zmq-client/composer.json zmq-client/composer.lock $APP_HOME/zmq-client/
RUN cd $APP_HOME/zmq-client && composer install
COPY --chown=www-data zmq-server/composer.json zmq-server/composer.lock $APP_HOME/zmq-server/
RUN cd $APP_HOME/zmq-server && composer install

# Copy application files
COPY --chown=www-data ci4 $APP_HOME/ci4
COPY --chown=www-data zmq-client $APP_HOME/zmq-client
COPY --chown=www-data zmq-server $APP_HOME/zmq-server
COPY --chown=www-data --from=build-stage /vue/dist $APP_HOME/vue
COPY --chown=www-data docker/apache/index.php $APP_HOME

# CodeIgniter opens its file cache on every boot, spark included, and stops if the
# directory is missing. Its contents are gitignored, so do not rely on the checkout.
RUN mkdir -p $APP_HOME/ci4/writable/cache && chown www-data:www-data $APP_HOME/ci4/writable/cache

# Set TAG_NAME as Environment Variable
ARG BUILD_TAG_NAME=latest
ENV TAG_NAME ${BUILD_TAG_NAME}

## Git Commit
ARG BUILD_SHORT_SHA
ENV SHORT_SHA ${BUILD_SHORT_SHA}

# Launch the httpd in foreground
CMD rm -rf /run/apache2/*

# Start message broker subscriber. By extending entrypoint
COPY docker/entrypoint-wrapper /usr/local/bin/entrypoint-wrapper
RUN chmod 775 /usr/local/bin/entrypoint-wrapper
ENTRYPOINT [ "entrypoint-wrapper" ]

