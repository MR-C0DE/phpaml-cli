#!/usr/bin/env bash
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
fixture="$(mktemp -d "${TMPDIR:-/tmp}/phpaml-build-deploy.XXXXXX")"
trap 'rm -rf "$fixture"' EXIT HUP INT TERM
mkdir -p "$fixture/project/public" "$fixture/project/app" "$fixture/project/configs" \
  "$fixture/project/runtime/bin" "$fixture/project/runtime/phpstan/phpstan" \
  "$fixture/project/runtime/composer" \
  "$fixture/project/runtime/phpaml/view/src" "$fixture/project/runtime/phpaml/view/tests" \
  "$fixture/project/runtime/phpaml/engine/examples" "$fixture/project/tests" \
  "$fixture/project/deliverables" "$fixture/project/.github/workflows" "$fixture/home"
cp "$root/cli/"*.php "$fixture/project/runtime/bin/"
cp "$root/phpaml.json" "$fixture/project/phpaml.json"
printf '%s\n' '<?php' > "$fixture/project/public/index.php"
printf '%s\n' 'RewriteEngine On' 'RewriteRule ^ index.php [QSA,L]' > "$fixture/project/public/.htaccess"
printf '%s\n' '<?php exit(0);' > "$fixture/project/tests/run.php"
printf '%s\n' 'SECRET=yes' > "$fixture/project/.env"
printf '%s\n' 'archive-only' > "$fixture/project/deliverables/backup.zip"
printf '%s\n' 'dev-only' > "$fixture/project/runtime/phpstan/phpstan/phpstan.phar"
printf '%s\n' '<?php' > "$fixture/project/runtime/phpaml/view/src/View.php"
printf '%s\n' '<?php' > "$fixture/project/runtime/phpaml/view/tests/run.php"
printf '%s\n' 'example' > "$fixture/project/runtime/phpaml/engine/examples/demo.php"
printf '%s\n' 'docs' > "$fixture/project/runtime/phpaml/view/README.md"
printf '%s\n' 'workflow' > "$fixture/project/.github/workflows/test.yml"
cat > "$fixture/project/runtime/composer/autoload_files.php" <<'PHP'
<?php
return [
    'view' => $vendorDir . '/phpaml/view/src/functions.php',
    'phpstan' => $vendorDir . '/phpstan/phpstan/bootstrap.php',
];
PHP
cat > "$fixture/project/runtime/composer/autoload_static.php" <<'PHP'
<?php
final class ComposerStaticFixture {
    public static $files = [
        'view' => __DIR__ . '/../phpaml/view/src/functions.php',
        'phpstan' => __DIR__ . '/../phpstan/phpstan/bootstrap.php',
    ];
}
PHP

(cd "$fixture/project" && HOME="$fixture/home" AML_LANG=en php runtime/bin/aml.php build)
listing="$(unzip -l "$fixture/project/output/phpaml-build.zip")"
grep -q 'public/index.php' <<<"$listing"
grep -q 'runtime/phpaml/view/src/View.php' <<<"$listing"
manifest="$(unzip -p "$fixture/project/output/phpaml-build.zip" build-manifest.json)"
grep -q '"hashes"' <<<"$manifest"
grep -q '"public/index.php": "[a-f0-9]\{64\}"' <<<"$manifest"
if grep -qE '(^|/)(\.env|\.github/|tests/|docs/|examples/|deliverables/|runtime/phpstan/)|runtime/phpaml/view/README\.md' <<<"$listing"; then
  echo 'The production build contains forbidden files.' >&2
  exit 1
fi
for composer_map in runtime/composer/autoload_files.php runtime/composer/autoload_static.php; do
  contents="$(unzip -p "$fixture/project/output/phpaml-build.zip" "$composer_map")"
  grep -q 'phpaml/view/src/functions.php' <<<"$contents"
  if grep -q 'phpstan/phpstan/bootstrap.php' <<<"$contents"; then
    echo "The production Composer map still references PHPStan: $composer_map" >&2
    exit 1
  fi
done
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
$verifiedRelease = $root . '/verified-release';
mkdir($verifiedRelease, 0777, true);
file_put_contents($verifiedRelease . '/app.php', 'verified');
file_put_contents($verifiedRelease . '/build-manifest.json', json_encode([
    'files' => ['app.php'],
    'hashes' => ['app.php' => hash_file('sha256', $verifiedRelease . '/app.php')],
]));
passthru(deployVerifyReleaseCommand($verifiedRelease), $verifyStatus);
if ($verifyStatus !== 0) exit(21);
file_put_contents($verifiedRelease . '/app.php', 'tampered');
passthru(deployVerifyReleaseCommand($verifiedRelease), $tamperedStatus);
if ($tamperedStatus === 0) exit(22);
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
    $hashes = [
        $sourceFile => hash_file('sha256', $build . '/' . $sourceFile),
        'public/index.php' => hash_file('sha256', $build . '/public/index.php'),
    ];
    file_put_contents($build . '/build-manifest.json', json_encode(['files' => [$sourceFile, 'public/index.php'], 'hashes' => $hashes]));
    $newManifest = deployManifest($build . '/build-manifest.json');
    $partition = deployPartitionFiles($newManifest['files']);
    if (!in_array($sourceFile, $partition['private'], true) || !in_array('public/index.php', $partition['public'], true)) exit(8);
    $previousManifest = ['files' => [$sourceFile, 'public/index.php', 'public/debug.php'], 'hashes' => [$sourceFile => $hashes[$sourceFile], 'public/index.php' => str_repeat('0', 64)]];
    $changes = deployManifestChanges($newManifest, $previousManifest);
    if ($changes['added'] !== [] || $changes['modified'] !== ['public/index.php'] || $changes['removed'] !== ['public/debug.php'] || $changes['unchanged'] !== [$sourceFile]) exit(15);
    $commands = deploySftpCommands($build, ['path' => '/remote/private', 'public_path' => '/remote/public'], $previousManifest);
    $batch = implode("\n", $commands);
    if (str_contains($batch, 'put "' . $build . '/' . $sourceFile . '"') || !str_contains($batch, 'put "' . $build . '/public/index.php"') || !str_contains($batch, '-rm "/remote/public/debug.php"')) exit(9);
    if (!str_contains($batch, '-mkdir "/remote"') || !str_contains($batch, '-mkdir "/remote/private"')) exit(14);
    $putPosition = strpos($batch, 'put "' . $build . '/public/index.php"');
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
