#!/usr/bin/env bash
# tools/manual-generator/sync-bookstack.sh — push a generated manual into BookStack.
#   ./tools/manual-generator/sync-bookstack.sh --plugin moresecurity --locale en_GB \
#        --manual-dir docs/manual/en_GB
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"
set -a
source tools/manual-generator/manual.env
set +a
node tools/manual-generator/sync-bookstack.mjs "$@"
