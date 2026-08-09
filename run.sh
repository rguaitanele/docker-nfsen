#!/bin/bash
set -Eeuo pipefail

readonly NFSEN_BIN=/usr/local/nfsen/bin/nfsen
readonly NFSEN_CONFIG=/usr/local/nfsen/etc/nfsen.conf
readonly GENERATED_CONFIG=/tmp/nfsen.conf

shutdown() {
    echo "Encerrando NfSen..."
    "$NFSEN_BIN" stop || true
    if [[ -n "${apache_pid:-}" ]]; then
        kill -TERM "$apache_pid" 2>/dev/null || true
        wait "$apache_pid" 2>/dev/null || true
    fi
}
trap shutdown TERM INT EXIT

gen-nfsen-conf "$GENERATED_CONFIG"

# Um volume /data vazio esconde a estrutura criada durante o build. O instalador
# só roda na primeira inicialização do volume; nas demais, apenas reconfiguramos.
if [[ ! -f /data/nfsen/profiles-stat/live/profile.dat ]]; then
    echo "Inicializando o volume de dados do NfSen..."
    install -m 0644 "$GENERATED_CONFIG" /opt/nfsen-src/etc/nfsen.conf
    (cd /opt/nfsen-src && printf '\n' | ./install.pl etc/nfsen.conf)
    chown -R netflow:www-data /data/nfsen
    chmod -R g+rwX /data/nfsen
else
    install -m 0644 "$GENERATED_CONFIG" "$NFSEN_CONFIG"
    printf 'y\n' | "$NFSEN_BIN" reconfig
fi

ensure-nfsen-hints
"$NFSEN_BIN" start
apache2-foreground &
apache_pid=$!
wait "$apache_pid"
