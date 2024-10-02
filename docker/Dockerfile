# Build Vue app
FROM node:16.13.1-alpine AS build-stage
WORKDIR /vue
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
FROM php:8.3.8-alpine3.19

# Install OS dependencies
RUN apk add --update  \
    apache2 \
    zlib-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    icu-dev \
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
    php83-apache2 php83-mbstring php83-session php83-json php83-tokenizer php83-mysqli php83-pdo php83-pdo_mysql php83-intl \
    php83-curl php83-pecl-yaml php83-calendar php83-posix php83-zip php83-xml php83-simplexml php83-xmlwriter php83-ctype \
    php83-pecl-imagick php83-gd php83-bcmath \
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
RUN docker-php-ext-install mysqli pdo pdo_mysql intl zip pcntl soap sockets calendar posix ctype
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
RUN cp /usr/local/etc/php/conf.d/docker-php-ext-gd.ini /etc/php83/conf.d/gd.ini

# SQLSRV (MSSQL Driver)
RUN wget https://download.microsoft.com/download/8/6/8/868e5fc4-7bfe-494d-8f9d-115cbcdb52ae/msodbcsql18_18.1.2.1-1_amd64.apk && \
  wget https://download.microsoft.com/download/8/6/8/868e5fc4-7bfe-494d-8f9d-115cbcdb52ae/mssql-tools18_18.1.1.1-1_amd64.apk && \
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
  rm mssql-tools18_18.1.1.1-1_amd64.apk
RUN cp /usr/local/etc/php/conf.d/30-sqlsrv.ini /etc/php83/conf.d/sqlsrv.ini
RUN cp /usr/local/etc/php/conf.d/docker-php-ext-pdo_sqlsrv.ini /etc/php83/conf.d/pdo_sqlsrv.ini
RUN cp /usr/local/lib/php/extensions/no-debug-non-zts-20230831/sqlsrv.so /usr/lib/php83/modules/sqlsrv.so
RUN cp /usr/local/lib/php/extensions/no-debug-non-zts-20230831/pdo_sqlsrv.so /usr/lib/php83/modules/pdo_sqlsrv.so

# ZMQ Server
RUN wget https://archive.org/download/zeromq_4.1.4/zeromq-4.1.4.tar.gz \
     && tar -xvzf zeromq-4.1.4.tar.gz \
     && cd zeromq-4.1.4 \
     && ./configure \
     && make \
     && make install \
     && ldconfig / \
     && cd .. \
     && rm -fr zeromq-4.1.4.tar.gz zeromq-4.1.4/

# ZMQ PHP Extension
RUN git clone https://github.com/zeromq/php-zmq.git \
 && cd php-zmq \
 && phpize && ./configure \
 && make \
 && make install \
 && cd .. \
 && rm -fr php-zmq
RUN docker-php-ext-enable zmq
RUN cp /usr/local/etc/php/conf.d/docker-php-ext-zmq.ini /etc/php83/conf.d/50_zmq.ini
RUN cp /usr/local/lib/php/extensions/no-debug-non-zts-20230831/zmq.so /usr/lib/php83/modules/zmq.so

# yaml_parse
RUN pear config-set php_ini /etc/php7/php.ini
RUN pecl channel-update pecl.php.net
RUN pecl install yaml-2.2.2 && docker-php-ext-enable yaml

# Install gke-auth
ARG GKE_AUTH_ENABLED=false
RUN if [[ "$GKE_AUTH_ENABLED" == "TRUE" ]] ; then GOBIN=/usr/local/bin go install github.com/imjasonh/gke-auth@latest ; fi

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# NPM
ENV NODE_VERSION=16.13.1
RUN curl -o- https://raw.githubusercontent.com/creationix/nvm/v0.34.0/install.sh | sh
ENV NVM_DIR=/root/.nvm
RUN . "$NVM_DIR/nvm.sh" && nvm install ${NODE_VERSION}
RUN . "$NVM_DIR/nvm.sh" && nvm use v${NODE_VERSION}
RUN . "$NVM_DIR/nvm.sh" && nvm alias default v${NODE_VERSION}
ENV PATH="/root/.nvm/versions/node/v${NODE_VERSION}/bin/:${PATH}"
RUN node --version
RUN npm --version

RUN rm -rf /tmp/* /var/cache/apk/*

# Configure apache
COPY docker/apache/httpd.conf /etc/apache2/httpd.conf

# Forward request and error logs to docker log collector
RUN ln -sf /dev/stdout /var/log/apache2/access.log \
    && ln -sf /dev/stderr /var/log/apache2/error.log

# Copy application files
ENV APP_HOME /var/www/html
COPY --chown=www-data ci4 $APP_HOME/ci4
COPY --chown=www-data zmq-client $APP_HOME/zmq-client
COPY --chown=www-data zmq-server $APP_HOME/zmq-server
COPY --chown=www-data --from=build-stage /vue/dist $APP_HOME/vue
COPY docker/apache/index.php $APP_HOME

# Change ownership of our applications
RUN chown -R www-data:www-data /var/www

# Install componser dependencies
RUN cd $APP_HOME/ci4 && composer install
RUN cd $APP_HOME/zmq-client && composer install
RUN cd $APP_HOME/zmq-server && composer install

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

