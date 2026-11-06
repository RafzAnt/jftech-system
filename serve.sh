#!/usr/bin/env bash
# JFTECH backend server — LAN-friendly
# Starts the Laravel dev server so phones on the same Wi-Fi can reach it.
#
# Usage:  ./serve.sh            (auto-detects local IPv4, defaults to 8001)
#         ./serve.sh 8080       (custom port)
#
# In the employee app (Server settings) type only the IP address shown below; the app adds http://, the port and /backend/api.
# You may need to allow the port through your firewall (for example: sudo ufw allow 8001/tcp).

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
echo "On your phone (same Wi-Fi), open the app > Server settings and type:"
echo "  $IP$([[ "$PORT" == "8001" ]] || echo ":$PORT")"
echo "(the full address is http://$IP:$PORT/backend/api)"
echo ""
echo "Press Ctrl+C to stop."
echo ""

if command -v ss >/dev/null 2>&1 && ss -ltn "sport = :$PORT" 2>/dev/null | grep -q LISTEN; then
  echo "Port $PORT is already in use. Stop the existing server first (Ctrl+C in the terminal that runs it), or run: ./serve.sh 8002" >&2
  if ss -ltn "sport = :$PORT" 2>/dev/null | grep -q "127.0.0.1:$PORT"; then
    echo "That server only listens on this computer (plain 'php artisan serve'), so a phone cannot reach it. ./serve.sh listens on the whole network." >&2
  fi
  exit 1
fi

# Cache Laravel's routes, views, and configuration before opening the dev
# server. This makes repeated requests noticeably faster over a forwarded port.
php artisan optimize --quiet

# The cached configuration would also be used by `php artisan test` (which would then run against the real database instead of the
# in-memory one), so it is cleared again when the server stops.
trap 'php artisan optimize:clear --quiet' EXIT
php artisan serve --host=0.0.0.0 --port="$PORT"
