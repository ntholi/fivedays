# syntax=docker/dockerfile:1.7
# One build, four targets: `app` (php-fpm, also used by migrate and cron), `web` (Caddy), `transfer`
# (cutover only) and `test` (PHPUnit). `core` holds everything except our plugins and config, so a
# plugin edit rebuilds only the last layers. Build args come from versions.env via bin/build.
ARG PHP_VERSION
ARG CADDY_VERSION
ARG COMPOSER_VERSION

FROM composer:${COMPOSER_VERSION} AS composer

FROM php:${PHP_VERSION}-fpm-bookworm AS core
ARG MOODLE_COMMIT
ARG OBJECTFS_COMMIT
ARG PHPREDIS_VERSION
ARG POSTGRES_MAJOR=17

RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends ca-certificates curl gnupg git unzip ghostscript libfcgi-bin \
    libpq-dev libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libxml2-dev libsodium-dev; \
  # pg_dump must match the server's major version, so it comes from the PostgreSQL apt repo.
  curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc | gpg --dearmor -o /usr/share/keyrings/pgdg.gpg; \
  echo "deb [signed-by=/usr/share/keyrings/pgdg.gpg] https://apt.postgresql.org/pub/repos/apt bookworm-pgdg main" >/etc/apt/sources.list.d/pgdg.list; \
  apt-get update; \
  apt-get install -y --no-install-recommends "postgresql-client-${POSTGRES_MAJOR}"; \
  docker-php-ext-configure gd --with-freetype --with-jpeg; \
  docker-php-ext-install -j"$(nproc)" pgsql mysqli intl zip gd soap exif opcache; \
  pecl install "redis-${PHPREDIS_VERSION}"; \
  docker-php-ext-enable redis; \
  rm -rf /var/lib/apt/lists/* /tmp/pear

COPY --from=composer /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/moodle
RUN set -eux; \
  git init -q .; \
  git remote add origin https://github.com/moodle/moodle.git; \
  git fetch -q --depth 1 origin "${MOODLE_COMMIT}"; \
  git checkout -q FETCH_HEAD; \
  test "$(git rev-parse HEAD)" = "${MOODLE_COMMIT}"; \
  if [ ! -f vendor/autoload.php ]; then COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --classmap-authoritative --no-interaction --no-progress; fi; \
  mkdir -p public/admin/tool/objectfs; \
  git -C public/admin/tool/objectfs init -q; \
  git -C public/admin/tool/objectfs remote add origin https://github.com/catalyst/moodle-tool_objectfs.git; \
  git -C public/admin/tool/objectfs fetch -q --depth 1 origin "${OBJECTFS_COMMIT}"; \
  git -C public/admin/tool/objectfs checkout -q FETCH_HEAD; \
  test "$(git -C public/admin/tool/objectfs rev-parse HEAD)" = "${OBJECTFS_COMMIT}"; \
  rm -rf .git public/admin/tool/objectfs/.git /root/.composer

# Moodle's English language pack uses en_AU.UTF-8 (langconfig.php); PHPUnit refuses to run without it.
RUN set -eux; \
  apt-get update; \
  apt-get install -y --no-install-recommends locales; \
  sed -i 's/^# *\(en_AU.UTF-8\)/\1/; s/^# *\(en_US.UTF-8\)/\1/' /etc/locale.gen; \
  locale-gen; \
  rm -rf /var/lib/apt/lists/*

ENV MOODLE_ROOT=/var/www/moodle

# Moodle's dev dependencies for the `test` target, kept out of the plugin layers so they stay cached.
FROM core AS testdeps
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --no-progress

FROM core AS app
ARG MOODLE_COMMIT
ARG OBJECTFS_COMMIT
COPY plugins/local_fivedays/ public/local/fivedays/
COPY plugins/gradingform_fivedays/ public/grade/grading/form/fivedays/
COPY docker/config.php config.php
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-moodle.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-moodle.conf
COPY docker/migrate.sh /usr/local/bin/moodle-migrate
COPY docker/cron.sh /usr/local/bin/moodle-cron

RUN set -eux; \
  rm -rf public/local/fivedays/.github public/grade/grading/form/fivedays/.github; \
  find public/local/fivedays public/grade/grading/form/fivedays -name '*.bat' -delete; \
  chown -R root:root /var/www/moodle; \
  chmod -R a+rX /var/www/moodle; \
  mkdir -p /var/moodledata /backups; \
  chown www-data:www-data /var/moodledata /backups; \
  chmod 0770 /var/moodledata /backups

ENV PHP_OPCACHE_VALIDATE=0 \
    FPM_MAX_CHILDREN=10

LABEL org.opencontainers.image.source="https://github.com/ntholi/fivedays" \
      org.opencontainers.image.title="fivedays-moodle" \
      moodle.commit="${MOODLE_COMMIT}" \
      objectfs.commit="${OBJECTFS_COMMIT}"

USER www-data
CMD ["php-fpm"]

# One-off MariaDB → Postgres transfer at cutover (and rehearsals). dbtransfer refuses unless the code's
# schema equals the source's (lib/dtl/database_exporter.php), so this is `app` without the plugins prod
# doesn't have yet; migrate installs them after the transfer.
FROM app AS transfer
USER root
RUN rm -rf public/admin/tool/objectfs
USER www-data

# CI and `just test`: plugin PHPUnit tests against Postgres. Adds Moodle's dev dependencies.
FROM app AS test
USER root
COPY --from=testdeps /var/www/moodle/vendor /var/www/moodle/vendor
RUN set -eux; \
  mkdir -p /var/phpunitdata; \
  chown www-data:www-data /var/phpunitdata; \
  chown -R www-data:www-data /var/www/moodle
USER www-data
ENV MOODLE_PHPUNIT=1

FROM caddy:${CADDY_VERSION}-alpine AS web
COPY --from=app /var/www/moodle/public /var/www/moodle/public
COPY docker/Caddyfile /etc/caddy/Caddyfile
