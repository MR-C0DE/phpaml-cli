#!/bin/sh
set -eu

ROOT=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
FIXTURE=$(mktemp -d "${TMPDIR:-/tmp}/phpaml-doctor-test.XXXXXX")
trap 'rm -rf "$FIXTURE"' EXIT HUP INT TERM

mkdir -p "$FIXTURE/aml_env/bin" "$FIXTURE/aml_env/tmp" \
    "$FIXTURE/aml_env/cache" "$FIXTURE/runtime/composer"
cp "$ROOT/cli/aml.php" "$FIXTURE/aml_env/bin/aml.php"
cp "$ROOT/cli/ai-debug.php" "$FIXTURE/aml_env/bin/ai-debug.php"
cp "$ROOT/info.json" "$FIXTURE/info.json"
touch "$FIXTURE/runtime/composer/composer.phar"

HEALTHY=$(cd "${TMPDIR:-/tmp}" && AML_LANG=fr php "$FIXTURE/aml_env/bin/aml.php" doctor --offline --json)
printf '%s\n' "$HEALTHY" | grep -q '"healthy": true'
printf '%s\n' "$HEALTHY" | grep -q '"name": "Extensions PHP"'

rm -f "$FIXTURE/runtime/composer/composer.phar"
if BROKEN=$(cd "${TMPDIR:-/tmp}" && AML_LANG=fr php "$FIXTURE/aml_env/bin/aml.php" doctor --offline --port 70000 --json); then
    echo "aml doctor devait signaler l'installation incomplète." >&2
    exit 1
fi
printf '%s\n' "$BROKEN" | grep -q '"healthy": false'
printf '%s\n' "$BROKEN" | grep -q 'Composer privé'
printf '%s\n' "$BROKEN" | grep -q 'le port doit être compris entre 1 et 65535'

touch "$FIXTURE/runtime/composer/composer.phar"
ENGLISH=$(cd "${TMPDIR:-/tmp}" && AML_LANG=en php "$FIXTURE/aml_env/bin/aml.php" doctor --offline --json)
printf '%s\n' "$ENGLISH" | grep -q '"name": "PHP extensions"'
printf '%s\n' "$ENGLISH" | grep -q '"message": "all present"'
if printf '%s\n' "$ENGLISH" | grep -q 'Operation not permitted'; then
    printf '%s\n' "$ENGLISH" | grep -q 'opening forbidden by the environment'
    if printf '%s\n' "$ENGLISH" | grep -q '127.0.0.1:8000 in use'; then
        echo "A sandbox restriction must not be reported as an occupied port." >&2
        exit 1
    fi
fi

BLOCKED="$FIXTURE/blocked-cache"
mkdir -p "$BLOCKED"
chmod 0555 "$BLOCKED"
if BLOCKED_RESULT=$(cd "${TMPDIR:-/tmp}" && AML_CACHE_HOME="$BLOCKED" AML_LANG=en php "$FIXTURE/aml_env/bin/aml.php" doctor --offline --json); then
    echo "aml doctor should report a genuinely read-only cache." >&2
    exit 1
fi
printf '%s\n' "$BLOCKED_RESULT" | grep -q '"name": "Cache AML"'
printf '%s\n' "$BLOCKED_RESULT" | grep -q '"status": "error"'
chmod 0755 "$BLOCKED"

mkdir -p "$FIXTURE/project/public" "$FIXTURE/project/configs" \
    "$FIXTURE/project/aml_env/framework" "$FIXTURE/project/aml_env/storage/cache"
cp "$ROOT/info.json" "$FIXTURE/project/info.json"
touch "$FIXTURE/project/public/index.php" "$FIXTURE/project/configs/app.php" \
    "$FIXTURE/project/aml_env/framework/Autoloader.php" "$FIXTURE/project/aml_env/autoload.php"
cat > "$FIXTURE/project/.env" <<'ENV'
APP_ENV=production
APP_DEBUG=true
APP_URL=http://example.test
APP_KEY=short
ENV
if PRODUCTION=$(cd "$FIXTURE/project" && AML_LANG=fr php "$FIXTURE/aml_env/bin/aml.php" doctor --offline --production --json); then
    echo "aml doctor --production devait refuser la configuration non sécurisée." >&2
    exit 1
fi
printf '%s\n' "$PRODUCTION" | grep -q '"name": "Mode debug"'
printf '%s\n' "$PRODUCTION" | grep -q '"name": "HTTPS"'
printf '%s\n' "$PRODUCTION" | grep -q '"name": "Secret applicatif"'

echo "Tests aml doctor réussis."
