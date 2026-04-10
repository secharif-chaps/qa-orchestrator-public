#syntax=docker/dockerfile:1

# Versions
FROM dunglas/frankenphp:1-php8.4 AS frankenphp_upstream

# The different stages of this Dockerfile are meant to be built into separate images
# https://docs.docker.com/develop/develop-images/multistage-build/#stop-at-a-specific-build-stage
# https://docs.docker.com/compose/compose-file/#target


# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base

ARG USER_ID=1000
ARG GROUP_ID=1000
ARG USER=basil

WORKDIR /app

# persistent / runtime deps
# hadolint ignore=DL3008
RUN apt-get update && apt-get install --no-install-recommends -y \
		acl \
		file \
		gettext \
		git \
	&& rm -rf /var/lib/apt/lists/*

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1

COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/bin/install-php-extensions

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
		bcmath \
		amqp \
		redis \
		pcntl \
		excimer \
		xsl \
		gmp \
		pdo_pgsql

RUN set -eux; \
	groupadd -g ${GROUP_ID} ${USER} && \
	useradd -u ${USER_ID} -g ${USER} ${USER} --home-dir /app && \
	# Add additional capability to bind to port 80 and 443
	setcap CAP_NET_BIND_SERVICE=+eip /usr/local/bin/frankenphp && \
    chown -R ${USER_ID}:${GROUP_ID} /config/caddy && \
    chown -Rf ${USER_ID}:${GROUP_ID} /data /app

COPY --link frankenphp/conf.d/app.ini $PHP_INI_DIR/conf.d/000-app.ini
COPY --chown=${USER_ID}:${GROUP_ID} --link --chmod=755 frankenphp/docker-entrypoint.sh /usr/local/bin/docker-entrypoint
COPY --chown=${USER_ID}:${GROUP_ID} --link frankenphp/Caddyfile /etc/caddy/Caddyfile
COPY --chown=${USER_ID}:${GROUP_ID} --link frankenphp/keycloak.Caddyfile /etc/caddy/keycloak.Caddyfile
COPY --chown=${USER_ID}:${GROUP_ID} --link frankenphp/n8n.Caddyfile /etc/caddy/n8n.Caddyfile

ENTRYPOINT ["docker-entrypoint"]

HEALTHCHECK --start-period=300s CMD curl -f --http1.1 http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile" ]

# Dev FrankenPHP image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
	install-php-extensions \
		xdebug

RUN chmod go+w /usr/local/share/ca-certificates /etc/ssl/certs

COPY --link frankenphp/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/

USER ${USER}

CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--watch" ]

# Prod FrankenPHP image
FROM frankenphp_base AS frankenphp_prod

ARG VERSION=dev
LABEL org.opencontainers.image.title="chapsmind-target" \
      org.opencontainers.image.description="ChapsMind Target module — competitive intelligence and strategic monitoring" \
      org.opencontainers.image.version="${VERSION}" \
      org.opencontainers.image.vendor="ChapsVision" \
      org.opencontainers.image.authors="ChapsVision <dev-chapsmind@chapsvision.com>" \
      org.opencontainers.image.url="https://git.mediaspeech.com/chapsmind/chapsmind" \
      org.opencontainers.image.source="https://git.mediaspeech.com/chapsmind/chapsmind/-/tree/main/apps/target" \
      org.opencontainers.image.licenses="Proprietary" \
      org.opencontainers.image.base.name="docker.io/dunglas/frankenphp:1-php8.4"

ENV APP_ENV=prod
ENV APP_VERSION=${VERSION}
ENV FRANKENPHP_CONFIG="import worker.Caddyfile"

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

RUN chmod go+w /usr/local/share/ca-certificates /etc/ssl/certs

COPY --chown=${USER_ID}:${GROUP_ID} --link frankenphp/conf.d/app.prod.ini $PHP_INI_DIR/conf.d/
COPY --chown=${USER_ID}:${GROUP_ID} --link frankenphp/worker.Caddyfile /etc/caddy/worker.Caddyfile

USER ${USER}

# prevent the reinstallation of vendors at every changes in the source code
COPY --chown=${USER_ID}:${GROUP_ID} --link composer.* symfony.* ./
RUN set -eux; \
	composer install --no-cache --prefer-dist --no-dev --no-autoloader --no-scripts --no-progress

# copy sources
COPY --chown=${USER_ID}:${GROUP_ID} --link . ./
RUN rm -Rf frankenphp/

RUN set -eux; \
	mkdir -p var/cache var/log; \
	composer dump-autoload --classmap-authoritative --no-dev; \
	composer run-script --no-dev post-install-cmd; \
	chmod +x bin/console; sync;
