#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d "${TMPDIR:-/tmp}/phpaml-build-deploy.XXXXXX")"
trap 'rm -rf "$fixture"' EXIT HUP INT TERM
mkdir -p "$fixture/project/public" "$fixture/project/app" "$fixture/project/configs" \
  "$fixture/project/aml_env/bin" "$fixture/project/tests" "$fixture/project/deliverables" "$fixture/home"
cp "$root/cli/"*.php "$fixture/project/aml_env/bin/"
cp "$root/info.json" "$fixture/project/info.json"
printf '%s\n' '<?php' > "$fixture/project/public/index.php"
printf '%s\n' 'RewriteEngine On' 'RewriteRule ^ index.php [QSA,L]' > "$fixture/project/public/.htaccess"
printf '%s\n' '<?php exit(0);' > "$fixture/project/tests/run.php"
printf '%s\n' 'SECRET=yes' > "$fixture/project/.env"
printf '%s\n' 'archive-only' > "$fixture/project/deliverables/backup.zip"

(cd "$fixture/project" && HOME="$fixture/home" AML_LANG=en php aml_env/bin/aml.php build)
listing="$(unzip -l "$fixture/project/output/phpaml-build.zip")"
grep -q 'public/index.php' <<<"$listing"
if grep -qE '(^|/)(\.env|tests/|deliverables/)' <<<"$listing"; then
  echo 'The production build contains forbidden files.' >&2
  exit 1
fi
(cd "$fixture/project/output" && shasum -a 256 -c phpaml-build.zip.sha256)

HOME="$fixture/home" AML_LANG=en php "$fixture/project/aml_env/bin/aml.php" \
  deploy:configure production --host example.com --user deploy --path /srv/site --key /tmp/key
if [[ "$(uname -s)" == Darwin ]]; then
  permissions="$(stat -f '%Lp' "$fixture/home/.phpaml/deploy.json")"
else
  permissions="$(stat -c '%a' "$fixture/home/.phpaml/deploy.json")"
fi
test "$permissions" = 600
! grep -qiE 'password|secret' "$fixture/home/.phpaml/deploy.json"
HOME="$fixture/home" AML_LANG=en php "$fixture/project/aml_env/bin/aml.php" \
  deploy:configure shared --host example.com --user deploy --path /srv/site \
  --strategy public-html --public-path /home/deploy/domains/example.com/public_html --key /tmp/key
grep -q '"strategy": "public-html"' "$fixture/home/.phpaml/deploy.json"

activation="$fixture/activation.php"
cat > "$activation" <<'PHP'
<?php
function fail(string $message): never { throw new RuntimeException($message); }
function optionValue(array $arguments, string $option): ?string { return null; }
function output(?string $message = null): void {}
require $argv[1];
$root = $argv[2];
$release = '20260812-190000';
$directory = $root . '/releases/' . $release;
mkdir($directory, 0777, true);
mkdir($root . '/current', 0777, true);
file_put_contents($root . '/current/default.php', 'host default');
$command = deployReleaseActivationCommand(['path' => $root], $directory, $release);
passthru($command, $status);
if ($status !== 0 || !is_link($root . '/current')) exit(1);
if (readlink($root . '/current') !== $directory) exit(2);
if (!is_file($root . '/current.pre-aml-' . $release . '/default.php')) exit(3);

$public = $root . '/unrelated-public/index.php';
mkdir(dirname($public), 0777, true);
file_put_contents($public, "<?php\n\$root = dirname(__DIR__);\n");
$command = deployPublicRootCommand($public, $root . '/private-app');
passthru($command, $status);
if ($status !== 0) exit(4);
$contents = file_get_contents($public);
if (!str_contains($contents, "\$root = '" . $root . "/private-app';")) exit(5);

$sftpIndex = $root . '/sftp-public/index.php';
mkdir(dirname($sftpIndex), 0777, true);
file_put_contents($sftpIndex, "<?php\n\$root = dirname(__DIR__);\n");
if (!deployRewritePublicRoot($sftpIndex, '/remote/private-app')) exit(6);
if (!str_contains(file_get_contents($sftpIndex), "\$root = '/remote/private-app';")) exit(7);
PHP
php "$activation" "$root/cli/deploy.php" "$fixture/remote/app"
echo 'Build and deploy smoke: OK'
