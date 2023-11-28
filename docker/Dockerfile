# Build Vue app
FROM node:16.13.1-alpine as build-stage
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
FROM php:8.0-alpine3.16

RUN echo "@testing http://dl-cdn.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories

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
    libxml2-dev \
    libzip-dev \
    unzip \
    nano \
    zip \
    libxml2-dev \
    wget \
    git \
    curl-dev@testing \
    yaml-dev \
    php8-apache2 php8-mbstring php8-session php8-json php8-tokenizer php8-mysqli php8-pdo php8-pdo_mysql php8-intl \
    php8-curl php8-pecl-yaml php8-calendar php8-posix php8-zip php8-xml php8-simplexml php8-xmlwriter php8-ctype \
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
RUN docker-php-ext-install bcmath gd mysqli pdo pdo_mysql intl zip pcntl soap sockets calendar posix ctype && \
	pecl install imagick curl
RUN docker-php-ext-enable imagick pdo_mysql intl posix
RUN docker-php-ext-configure calendar
RUN docker-php-ext-configure gd \
      --enable-gd \
      --with-freetype=/usr/include/ \
      --with-jpeg=/usr/include/
RUN rm -rf /tmp/* /var/cache/apk/*

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
RUN rm -rf /tmp/* /var/cache/apk/*
RUN cp /usr/local/etc/php/conf.d/docker-php-ext-zmq.ini /etc/php8/conf.d/50_zmq.ini
RUN cp /usr/local/lib/php/extensions/no-debug-non-zts-20200930/zmq.so /usr/lib/php8/modules/zmq.so

# yaml_parse
RUN pear config-set php_ini /etc/php7/php.ini
RUN pecl channel-update pecl.php.net
RUN pecl install yaml-2.2.2 && docker-php-ext-enable yaml

# New Relic
# Phpize issue: https://discuss.newrelic.com/t/alpine-linux-3-11-newrelic-php-agent-install-fails-to-detect-os-correctly-when-dpkg-installed/101360
RUN rm -rf /etc/dpkg
ARG NEW_RELIC_ENABLED=false
ARG NEW_RELIC_LICENSE_KEY
ARG NEW_RELIC_APP_NAME
ENV NEW_RELIC_VERSION newrelic-php5-9.18.1.303-linux-musl
RUN if [[ "$NEW_RELIC_ENABLED" == "TRUE" ]] ; then \
    mkdir -p /var/log/newrelic /var/run/newrelic && \
    touch /var/log/newrelic/php_agent.log /var/log/newrelic/newrelic-daemon.log && \
    chmod -R g+ws /tmp /var/log/newrelic/ /var/run/newrelic/ && \
    chown -R 1001:0 /tmp /var/log/newrelic/ /var/run/newrelic/ && \
\
    # Download and install New Relic binary
    cd /tmp && curl -sS "https://download.newrelic.com/php_agent/archive/9.18.1.303/${NEW_RELIC_VERSION}.tar.gz" | gzip -dc | tar xf - && \
    cd "${NEW_RELIC_VERSION}" && \
    NR_INSTALL_SILENT=true ./newrelic-install install && \
    rm -f /var/run/newrelic-daemon.pid && \
    rm -f /tmp/.newrelic.sock && \
\
    # For Newrelic's APM (Application Monitoring) license and appname are required.
    # Environment variables `NEW_RELIC_LICENSE_KEY` and `NEW_RELIC_APP_NAME` are required when buidling Docker image,
    # so you must set them in your **BuildConfig** Environments.
    sed -i \
        -e "s/newrelic.license =.*/newrelic.license = ${NEW_RELIC_LICENSE_KEY}/" \
        -e "s/newrelic.appname =.*/newrelic.appname = ${NEW_RELIC_APP_NAME}/" \
        /usr/local/etc/php/conf.d/newrelic.ini \
    ; fi

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

