#!/usr/bin/env bash
# JFTECH backend server — LAN-friendly
# Starts the Laravel dev server so phones on the same Wi-Fi can reach it.
#
# Usage:  ./serve.sh            (auto-detects local IPv4, defaults to 8001)
#         ./serve.sh 8080       (custom port)
#
# After starting, open the URL shown in the terminal on your phone.
# You may need to allow port 8000 through your firewall.

set -euo pipefail

PORT="${1:-8001}"

# Pick the first non-loopback IPv4 address on Linux.
if [[ -n "${HOST:-}" ]]; then
  IP="$HOST"
else
  IP=$(hostname -I 2>/dev/null | awk '{print $1}')
  if [[ -z "$IP" ]]; then
    IP="0.0.0.0"
  fi
fi

echo "Starting JFTECH backend server..."
echo "  Host:  $IP"
echo "  Port:  $PORT"
echo ""
echo "On your phone, use this URL in the app settings:"
echo "  http://$IP:$PORT/backend/api"
echo ""
echo "Press Ctrl+C to stop."
echo ""

if command -v ss >/dev/null 2>&1 && ss -ltn "sport = :$PORT" 2>/dev/null | grep -q LISTEN; then
  echo "Port $PORT is already in use. Stop the existing server first, or run: ./serve.sh 8002" >&2
  exit 1
fi

# Cache Laravel's routes, views, and configuration before opening the dev
# server. This makes repeated requests noticeably faster over a forwarded port.
php artisan optimize --quiet

exec php artisan serve --host=0.0.0.0 --port="$PORT"
