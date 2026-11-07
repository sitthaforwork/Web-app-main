#!/bin/sh
set -e

# Disable and remove all MPM configs then enable prefork
rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true

# Set Railway dynamic port
TARGET_PORT="${PORT:-8080}"
echo "Listen ${TARGET_PORT}" > /etc/apache2/ports.conf
echo "Starting Apache on port ${TARGET_PORT}..."

exec apache2-foreground
