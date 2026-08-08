FROM php:8.2-apache-bookworm

ARG NFDUMP_VERSION=1.7.8
ARG NFSEN_VERSION=1.3.11

ENV NFSEN_SOURCES_FILE=/etc/nfsen/sources.json \
    NFSEN_VERSION=${NFSEN_VERSION} \
    NFDUMP_MAJOR=7

COPY Nfsync.pm.patch /tmp/Nfsync.pm.patch

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        autoconf automake bison build-essential ca-certificates curl flex \
        libbz2-1.0 libbz2-dev liblz4-1 liblz4-dev libmailtools-perl librrd-dev \
        librrds-perl libsocket6-perl libtool libzstd-dev libzstd1 make pkg-config rrdtool tzdata; \
    useradd --uid 1000 --gid www-data --home-dir /nonexistent --no-create-home netflow; \
    curl -fsSL "https://github.com/phaag/nfdump/archive/refs/tags/v${NFDUMP_VERSION}.tar.gz" -o /tmp/nfdump.tar.gz; \
    mkdir /tmp/nfdump; \
    tar -xzf /tmp/nfdump.tar.gz -C /tmp/nfdump --strip-components=1; \
    cd /tmp/nfdump; \
    ./autogen.sh; \
    ./configure --enable-nfprofile; \
    make -j"$(nproc)"; \
    make install; \
    ldconfig; \
    curl -fsSL "https://github.com/phaag/nfsen/archive/refs/tags/v.${NFSEN_VERSION}.tar.gz" -o /tmp/nfsen.tar.gz; \
    mkdir /opt/nfsen-src; \
    tar -xzf /tmp/nfsen.tar.gz -C /opt/nfsen-src --strip-components=1; \
    patch -d /opt/nfsen-src -p1 < /tmp/Nfsync.pm.patch; \
    rm -rf /tmp/nfdump /tmp/nfdump.tar.gz /tmp/nfsen.tar.gz; \
    docker-php-ext-install sockets; \
    apt-get purge -y --auto-remove autoconf automake bison build-essential flex libbz2-dev liblz4-dev librrd-dev libtool libzstd-dev make pkg-config; \
    rm -rf /var/lib/apt/lists/*

COPY nfsen.conf /etc/nfsen/nfsen.conf.template
COPY sources.json /etc/nfsen/sources.json
COPY gen_conf.php /usr/local/bin/gen-nfsen-conf
COPY ensure_hints.pl /usr/local/bin/ensure-nfsen-hints
COPY run.sh /usr/local/bin/docker-entrypoint

RUN chmod +x /usr/local/bin/gen-nfsen-conf /usr/local/bin/ensure-nfsen-hints /usr/local/bin/docker-entrypoint; \
    /usr/local/bin/gen-nfsen-conf /opt/nfsen-src/etc/nfsen.conf; \
    cd /opt/nfsen-src; \
    printf '\n' | ./install.pl etc/nfsen.conf; \
    ln -sf nfsen.php /var/www/nfsen/index.php; \
    cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"; \
    printf '%s\n' 'date.timezone=America/Sao_Paulo' >> "$PHP_INI_DIR/php.ini"; \
    printf '%s\n' \
        'Alias /nfsen/ /var/www/nfsen/' \
        '<Directory /var/www/nfsen>' \
        '    DirectoryIndex index.php' \
        '    Require all granted' \
        '</Directory>' > /etc/apache2/conf-available/nfsen.conf; \
    a2enconf nfsen; \
    chown -R www-data:www-data /var/www/nfsen

EXPOSE 80 4445-4453/udp
VOLUME ["/data"]

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD /usr/local/nfsen/bin/nfsen status 2>/dev/null | grep -q 'nfsen daemon:.*is running' || exit 1

STOPSIGNAL SIGTERM
ENTRYPOINT ["/usr/local/bin/docker-entrypoint"]
