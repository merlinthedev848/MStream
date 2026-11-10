# syntax=docker/dockerfile:1

#
# MariaDB dependencies build step
#
FROM mariadb:lts-noble AS mariadb

#
# PHP Extension Installer build step
#
FROM mlocati/php-extension-installer AS php-extension-installer

# ---

FROM scratch AS dependencies

# Add PHP extension installer tool
COPY --from=php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Add MariaDB dependencies
COPY --from=mariadb /usr/local/bin/healthcheck.sh /usr/local/bin/db_healthcheck.sh
COPY --from=mariadb /usr/local/bin/docker-entrypoint.sh /usr/local/bin/db_entrypoint.sh

#
# Final build image
#
FROM php:8.5.12-fpm-trixie AS pre-final

ENV TZ="UTC" \
    LANGUAGE="en_US.UTF-8" \
    LC_ALL="en_US.UTF-8" \
    LANG="en_US.UTF-8" \
    LC_TYPE="en_US.UTF-8"

COPY --link --from=dependencies / /

# Run base build process
RUN --mount=type=bind,source=./util/docker/common,target=/bd_build,rw \
    bash /bd_build/prepare.sh && \
    bash /bd_build/add_user.sh && \
    bash /bd_build/cleanup.sh

# Build each set of dependencies in their own step for cacheability.
RUN --mount=type=bind,source=./util/docker/common,target=/bd_build,rw \
    --mount=type=bind,source=./util/docker/supervisor,target=/bd_build/supervisor,rw \
    bash /bd_build/supervisor/setup.sh && \
    bash /bd_build/cleanup.sh

RUN --mount=type=bind,source=./util/docker/common,target=/bd_build,rw \
    --mount=type=bind,source=./util/docker/stations,target=/bd_build/stations,rw \
    bash /bd_build/stations/setup.sh && \
    bash /bd_build/cleanup.sh

RUN --mount=type=bind,source=./util/docker/common,target=/bd_build,rw \
    --mount=type=bind,source=./util/docker/web,target=/bd_build/web,rw \
    bash /bd_build/web/setup.sh && \
    bash /bd_build/cleanup.sh

RUN --mount=type=bind,source=./util/docker/common,target=/bd_build,rw \
    --mount=type=bind,source=./util/docker/mariadb,target=/bd_build/mariadb,rw \
    bash /bd_build/mariadb/setup.sh && \
    bash /bd_build/cleanup.sh

RUN --mount=type=bind,source=./util/docker/common,target=/bd_build,rw \
    --mount=type=bind,source=./util/docker/redis,target=/bd_build/redis,rw \
    bash /bd_build/redis/setup.sh && \
    bash /bd_build/cleanup.sh

RUN --mount=type=bind,source=./util/docker/common,target=/bd_build,rw \
    bash /bd_build/chown_dirs.sh

USER mstream

RUN touch /var/mstream/.docker

USER root

VOLUME "/var/mstream/stations"
VOLUME "/var/mstream/backups"
VOLUME "/var/lib/mysql"
VOLUME "/var/mstream/storage/acme"
VOLUME "/var/mstream/storage/geoip"
VOLUME "/var/mstream/storage/rsas"
VOLUME "/var/mstream/storage/sftpgo"
VOLUME "/var/mstream/storage/shoutcast2"
VOLUME "/var/mstream/storage/stereo_tool"
VOLUME "/var/mstream/storage/uploads"

EXPOSE 80 443 2022

# Sensible default environment variables.
ENV LANG="en_US.UTF-8" \
    PATH="${PATH}:/var/mstream/storage/shoutcast2" \
    APPLICATION_ENV="production" \
    MYSQL_HOST="localhost" \
    MYSQL_PORT=3306 \
    MYSQL_USER="mstream" \
    MYSQL_PASSWORD="mstream_secret" \
    MYSQL_DATABASE="mstream" \
    ENABLE_REDIS="true" \
    REDIS_HOST="localhost" \
    REDIS_PORT=6379 \
    REDIS_DB=0 \
    NGINX_RADIO_PORTS="default" \
    NGINX_WEBDJ_PORTS="default" \
    COMPOSER_PLUGIN_MODE="false" \
    ADDITIONAL_MEDIA_SYNC_WORKER_COUNT=0 \
    PROFILING_EXTENSION_ENABLED=0 \
    PROFILING_EXTENSION_ALWAYS_ON=0 \
    PROFILING_EXTENSION_HTTP_KEY=dev \
    PROFILING_EXTENSION_HTTP_IP_WHITELIST=* \
    ENABLE_WEB_UPDATER="true"

#
# Development Build
#
FROM pre-final AS development

# Dev build step
COPY ./util/docker/common /bd_build/
COPY ./util/docker/dev /bd_build/dev

RUN bash /bd_build/dev/setup.sh \
    && bash /bd_build/cleanup.sh \
    && rm -rf /bd_build

USER mstream

WORKDIR /var/mstream/www

COPY --chown=mstream:mstream . .

RUN --mount=type=cache,target=/var/mstream/.composer/cache,uid=1000,gid=1000 \
    composer install --no-ansi --no-interaction

RUN --mount=type=cache,target=/var/mstream/.npm,uid=1000,gid=1000 \
    npm ci --include=dev --prefer-offline --no-audit

USER root

# Sensible default environment variables.
ENV APPLICATION_ENV="development" \
    PROFILING_EXTENSION_ENABLED=1 \
    ENABLE_WEB_UPDATER="false"

# Entrypoint and default command
ENTRYPOINT ["tini", "--", "/usr/local/bin/my_init"]
CMD ["--no-main-command"]

#
# Final build (Just environment vars and squishing the FS)
#
FROM pre-final AS final

LABEL org.opencontainers.image.title="MStream" \
      org.opencontainers.image.description="MStream - Enterprise Audio Broadcast & Streaming Suite" \
      org.opencontainers.image.source="https://github.com/merlinthedev848/MStream"

USER mstream

WORKDIR /var/mstream/www

COPY --chown=mstream:mstream . .

RUN --mount=type=cache,target=/var/mstream/.composer/cache,uid=1000,gid=1000 \
    composer install --no-dev --no-ansi --no-autoloader --no-interaction \
    && composer dump-autoload --optimize --classmap-authoritative

USER root

# Entrypoint and default command
ENTRYPOINT ["tini", "--", "/usr/local/bin/my_init"]
CMD ["--no-main-command"]
