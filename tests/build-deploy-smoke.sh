#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d "${TMPDIR:-/tmp}/phpaml-build-deploy.XXXXXX")"
trap 'rm -rf "$fixture"' EXIT HUP INT TERM
mkdir -p "$fixture/project/public" "$fixture/project/app" "$fixture/project/configs" \
  "$fixture/project/runtime/bin" "$fixture/project/tests" "$fixture/project/deliverables" "$fixture/home"
cp "$root/cli/"*.php "$fixture/project/runtime/bin/"
cp "$root/phpaml.json" "$fixture/project/phpaml.json"
printf '%s\n' '<?php' > "$fixture/project/public/index.php"
printf '%s\n' 'RewriteEngine On' 'RewriteRule ^ index.php [QSA,L]' > "$fixture/project/public/.htaccess"
printf '%s\n' '<?php exit(0);' > "$fixture/project/tests/run.php"
printf '%s\n' 'SECRET=yes' > "$fixture/project/.env"
printf '%s\n' 'archive-only' > "$fixture/project/deliverables/backup.zip"

(cd "$fixture/project" && HOME="$fixture/home" AML_LANG=en php runtime/bin/aml.php build)
listing="$(unzip -l "$fixture/project/output/phpaml-build.zip")"
grep -q 'public/index.php' <<<"$listing"
if grep -qE '(^|/)(\.env|tests/|deliverables/)' <<<"$listing"; then
  echo 'The production build contains forbidden files.' >&2
  exit 1
fi
(cd "$fixture/project/output" && shasum -a 256 -c phpaml-build.zip.sha256)
printf '%s\n' 'API_TOKEN=production-secret' > "$fixture/project/.env.production"
if (cd "$fixture/project" && HOME="$fixture/home" AML_LANG=en php runtime/bin/aml.php build >/dev/null 2>&1); then
  echo 'The production build accepted .env.production.' >&2
  exit 1
fi
rm "$fixture/project/.env.production"

HOME="$fixture/home" AML_LANG=en php "$fixture/project/runtime/bin/aml.php" \
  deploy:configure production --host example.com --user deploy --path /srv/site --key /tmp/key
if [[ "$(uname -s)" == Darwin ]]; then
  permissions="$(stat -f '%Lp' "$fixture/home/.phpaml/deploy.json")"
else
  permissions="$(stat -c '%a' "$fixture/home/.phpaml/deploy.json")"
fi
test "$permissions" = 600
! grep -qiE 'password|secret' "$fixture/home/.phpaml/deploy.json"
HOME="$fixture/home" AML_LANG=en php "$fixture/project/runtime/bin/aml.php" \
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
if (!is_dir($root)) mkdir($root, 0777, true);
$integrityArchive = $root . '/integrity.zip';
$integrityChecksum = $integrityArchive . '.sha256';
file_put_contents($integrityArchive, 'valid-build');
file_put_contents($integrityChecksum, hash_file('sha256', $integrityArchive) . "  integrity.zip\n");
deployVerifyBuildIntegrity($integrityArchive, $integrityChecksum);
file_put_contents($integrityArchive, 'tampered-build');
try { deployVerifyBuildIntegrity($integrityArchive, $integrityChecksum); exit(20); } catch (RuntimeException) {}
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

foreach (['mvc' => 'app/Controllers/HomeController.php', 'view' => 'src/views/pages/home/page.php'] as $kind => $sourceFile) {
    $build = $root . '/build-' . $kind;
    $private = $root . '/private-' . $kind;
    $documentRoot = $root . '/public-' . $kind;
    mkdir($build . '/public', 0777, true);
    mkdir(dirname($build . '/' . $sourceFile), 0777, true);
    file_put_contents($build . '/' . $sourceFile, $kind);
    file_put_contents($build . '/public/index.php', "<?php\n\$root = dirname(__DIR__);\n");
    file_put_contents($build . '/build-manifest.json', json_encode(['files' => [$sourceFile, 'public/index.php']]));
    $partition = deployPartitionFiles(deployManifestFiles($build . '/build-manifest.json'));
    if (!in_array($sourceFile, $partition['private'], true) || !in_array('public/index.php', $partition['public'], true)) exit(8);
    $commands = deploySftpCommands($build, ['path' => '/remote/private', 'public_path' => '/remote/public'], ['public/debug.php']);
    $batch = implode("\n", $commands);
    if (!str_contains($batch, $sourceFile) || !str_contains($batch, '-rm "/remote/public/debug.php"')) exit(9);
    $putPosition = strpos($batch, 'put "' . $build . '/' . $sourceFile . '"');
    $removePosition = strpos($batch, '-rm "/remote/public/debug.php"');
    $renamePosition = strpos($batch, 'rename "/remote/private/.phpaml-deploy-manifest.next.json"');
    if (!is_int($putPosition) || !is_int($removePosition) || !is_int($renamePosition) || !($putPosition < $removePosition && $removePosition < $renamePosition)) exit(12);
    mkdir($private, 0777, true); mkdir($documentRoot, 0777, true);
    file_put_contents($documentRoot . '/debug.php', 'obsolete');
    file_put_contents($private . '/.phpaml-deploy-manifest.json', json_encode(['files' => ['public/debug.php']]));
    passthru(deployManifestSyncCommand($build, $private, $documentRoot), $syncStatus);
    if ($syncStatus !== 0 || !is_file($private . '/' . $sourceFile) || !is_file($documentRoot . '/index.php') || is_file($documentRoot . '/debug.php')) exit(10);
}

$temporary = $root . '/phpaml-sftp-cleanup';
mkdir($temporary . '/nested', 0777, true); file_put_contents($temporary . '/nested/source.php', '<?php');
deployRemoveDirectory($temporary);
if (is_dir($temporary)) exit(11);

mkdir($root . '/releases/20260810-000000', 0777, true);
mkdir($root . '/releases/20260811-000000', 0777, true);
file_put_contents($root . '/releases/orphan.zip', 'zip');
passthru(deployPruneCommand(['path' => $root], 1), $pruneStatus);
if ($pruneStatus !== 0 || !is_dir($directory) || is_dir($root . '/releases/20260810-000000') || is_file($root . '/releases/orphan.zip')) exit(13);
PHP
php "$activation" "$root/cli/deploy.php" "$fixture/remote/app"
echo 'Build and deploy smoke: OK'
