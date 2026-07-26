#!/usr/bin/env bash
# Expose local WPSaleHub for Meta Messenger webhooks via your Cloudflare named tunnel.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
CONFIG="${ROOT}/cloudflared.messenger.yml"
HUB_PORT="${HUB_PORT:-8000}"
HOST="${MESSENGER_TUNNEL_HOST:-whatsapp.futureseedsbd.site}"
VERIFY_TOKEN="${META_MESSENGER_WEBHOOK_VERIFY_TOKEN:-webhook_token_test_01770989591}"

if ! command -v cloudflared >/dev/null 2>&1; then
  echo "Install cloudflared: brew install cloudflared"
  exit 1
fi

if ! curl -sS -o /dev/null --max-time 2 "http://127.0.0.1:${HUB_PORT}"; then
  echo "Hub not on :${HUB_PORT}. Start: php artisan serve --host=127.0.0.1 --port=${HUB_PORT}"
  exit 1
fi

# Warn if Docker stole :8000 (CompreFace).
if lsof -nP -iTCP:"${HUB_PORT}" -sTCP:LISTEN 2>/dev/null | grep -q com.docke; then
  echo "WARNING: Docker is also bound to :${HUB_PORT} (often CompreFace)."
  echo "Stop it: docker stop compreface-ui compreface-admin compreface-api compreface-postgres-db compreface-core"
fi

echo "Public webhook URL:"
echo "  https://${HOST}/api/webhooks/messenger"
echo "Verify token: ${VERIFY_TOKEN}"
echo
echo "Paste that URL into Meta App → Messenger → Webhooks, then keep this process running."
echo

exec cloudflared tunnel --config "$CONFIG" run
