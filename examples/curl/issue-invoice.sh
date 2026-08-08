#!/usr/bin/env sh
set -eu

curl --fail-with-body \
  -X POST "${TRIBUX_URL:-http://localhost:8080}/v1/invoices" \
  -H 'Content-Type: application/json' \
  -H 'Idempotency-Key: example-order-0001' \
  --data-binary @examples/invoice.minimal.json
