#!/usr/bin/env bash
# tools/manual-generator/run.sh — capture screenshots in a version-matched Playwright container.
#
#   ./tools/manual-generator/run.sh                        # en_GB against http://glpi
#   MANUAL_LOCALE=es_ES ./tools/manual-generator/run.sh
#   GLPI_NETWORK=domainmanager_default ./tools/manual-generator/run.sh --headed
#
set -euo pipefail
cd "$(git rev-parse --show-toplevel)"

# Trap 9: source manual.env FIRST. Every var below has an inline default so this script also
# runs without it — which is exactly how a missing `source` here goes unnoticed: it silently
# falls back to glpi_default/http://glpi instead of erroring, and every symptom that follows
# (login failures, session weirdness) looks like a GLPI bug instead of "wrong stack".
[[ -f tools/manual-generator/manual.env ]] && source tools/manual-generator/manual.env

MANUAL_LOCALE="${MANUAL_LOCALE:-en_GB}"
BASE_URL="${BASE_URL:-http://glpi}"
GLPI_NETWORK="${GLPI_NETWORK:-glpi_default}"
TZ="${TZ:-Europe/Madrid}"
ENGINE="${ENGINE:-podman}"

# The image tag must match @playwright/test exactly, or Chromium won't be found — and the
# error message reads like a missing install, which sends you looking in the wrong place.
if [[ ! -f node_modules/@playwright/test/package.json ]]; then
  echo "run 'npm ci' first (need node_modules/@playwright/test to derive the image tag)" >&2
  exit 1
fi
PW_VERSION="$(node -p "require('./node_modules/@playwright/test/package.json').version")"
IMAGE="mcr.microsoft.com/playwright:v${PW_VERSION}-noble"

# Restore the golden fixture so the run starts from known state. Do this outside the
# container: a failed capture must still leave the DB clean for the next attempt.
if [[ -x tools/manual-generator/fixtures/restore.sh ]]; then
  echo "==> restoring golden fixture"
  tools/manual-generator/fixtures/restore.sh
else
  echo "!!  tools/manual-generator/fixtures/restore.sh missing — screenshots will not be reproducible" >&2
fi

NET_ARGS=(--network "${GLPI_NETWORK}")
if [[ "${BASE_URL}" == *"host.containers.internal"* ]]; then
  NET_ARGS=(--add-host=host.containers.internal:host-gateway)
elif [[ "${BASE_URL}" == *"localhost"* || "${BASE_URL}" == *"127.0.0.1"* ]]; then
  NET_ARGS=(--network=host)
fi

echo "==> capturing ${MANUAL_LOCALE} from ${BASE_URL} using ${IMAGE}"
"${ENGINE}" run --rm -it \
  "${NET_ARGS[@]}" \
  --ipc=host \
  --security-opt seccomp=unconfined \
  -v "${PWD}:/work" \
  -w /work \
  -e BASE_URL="${BASE_URL}" \
  -e MANUAL_LOCALE="${MANUAL_LOCALE}" \
  -e TZ="${TZ}" \
  -e CI="${CI:-}" \
  "${IMAGE}" \
  npx playwright test --config tools/manual-generator/playwright.config.ts "$@"

# Note on ownership: rootless Podman maps container root to your host UID, so the PNGs come
# out owned by you. Do NOT add --userns=keep-id here — it usually breaks that mapping and
# makes /ms-playwright unreadable. On Docker, add: --user "$(id -u):$(id -g)".

echo "==> rendering the 4 manual files"
MANUAL_LOCALE="${MANUAL_LOCALE}" node tools/manual-generator/render-manual.mjs

git status --short docs/manual | grep -E '\.png$' >/dev/null \
  && echo "==> screenshots changed — review the diff before committing" \
  || echo "==> screenshots unchanged"
