// Verify the release archives through the real CLI and Composer, without source
// symlinks or changes to the source repositories' indexes.
import {execFileSync, spawnSync} from 'node:child_process';
import {createHash, randomBytes} from 'node:crypto';
import {cpSync, existsSync, lstatSync, mkdirSync, mkdtempSync, readFileSync, readdirSync, rmSync, writeFileSync} from 'node:fs';
import {dirname, resolve} from 'node:path';
import {fileURLToPath, pathToFileURL} from 'node:url';

const root = resolve(fileURLToPath(new URL('..', import.meta.url)));
const source = name => resolve(process.env[`PHPAML_${name.toUpperCase()}_SOURCE`] || resolve(root, `../phpaml-${name}`));
const json = file => JSON.parse(readFileSync(file, 'utf8'));
const cliVersion = json(resolve(root, 'phpaml.json')).version;
const output = resolve(process.env.PHPAML_RELEASE_OUTPUT || resolve(root, `dist/frontend-${cliVersion}`));
mkdirSync(output, {recursive: true});
const work = mkdtempSync(resolve(output, 'verification-'));
const logs = resolve(work, 'logs'); mkdirSync(logs);
const reportFile = resolve(output, 'verification.json');
writeFileSync(reportFile, JSON.stringify({status: 'in-progress', cli: cliVersion, logs, browsers: 'not-run'}, null, 2) + '\n');
process.on('exit', code => {
  if (code !== 0) {
    const failed = json(reportFile); failed.status = 'failed';
    writeFileSync(reportFile, JSON.stringify(failed, null, 2) + '\n');
  }
});
const sha = (file, algorithm = 'sha256') => createHash(algorithm).update(readFileSync(file)).digest('hex');
const run = (command, args, options = {}) => execFileSync(command, args, {encoding: 'utf8', maxBuffer: 16 * 1024 * 1024, ...options});
const archives = [];

function archive(directory, name, version) {
  const snapshot = resolve(work, `snapshot-${name}`); mkdirSync(snapshot);
  const files = run('git', ['ls-files', '-z', '--cached', '--others', '--exclude-standard', '--exclude=.integration/'], {cwd: directory}).split('\0').filter(Boolean);
  for (const relative of new Set(files)) {
    const from = resolve(directory, relative);
    if (!from.startsWith(directory + '/') || !existsSync(from)) continue;
    if (lstatSync(from).isSymbolicLink()) throw new Error(`Source symlink cannot be packaged: ${from}`);
    const to = resolve(snapshot, relative); mkdirSync(dirname(to), {recursive: true}); cpSync(from, to);
  }
  run('git', ['init', '--quiet', snapshot]);
  run('git', ['add', '--force', '--all'], {cwd: snapshot});
  const tree = run('git', ['write-tree'], {cwd: snapshot}).trim();
  const file = resolve(output, `${name}-${version}.zip`);
  run('git', ['archive', '--format=zip', `--prefix=${name}-${version}/`, `--output=${file}`, tree], {cwd: snapshot});
  rmSync(snapshot, {recursive: true});
  archives.push({name, version, file, sha256: sha(file), tree});
  return file;
}

function extract(zip, to) {
  mkdirSync(to, {recursive: true});
  run(process.env.PHP_BINARY || 'php', ['-r', '$z=new ZipArchive; if($z->open($argv[1])!==true || !$z->extractTo($argv[2])) exit(1);', zip, to]);
}

function step(name, command, args, options = {}) {
  console.log(name);
  const result = spawnSync(command, args, {encoding: 'utf8', maxBuffer: 16 * 1024 * 1024, ...options});
  writeFileSync(resolve(logs, `${name}.log`), String(result.stdout || '') + String(result.stderr || '') + (result.error?.message || ''));
  if (result.error || result.status !== 0) throw new Error(`${name} failed; see ${resolve(logs, `${name}.log`)}`, {cause: result.error});
  return result.stdout;
}

const php = process.env.PHP_BINARY || 'php';
step('version-policy', php, [resolve(root, 'tests/versioning-smoke.php')], {env: {...process.env,
  PHPAML_TEMPLATE_SOURCE: source('template'), PHPAML_VIEW_SOURCE: source('view'), PHPAML_ENGINE_SOURCE: source('engine')}});
step('engine-build-check', process.execPath, [resolve(source('engine'), 'scripts/build-runtime.mjs'), '--check']);
const packages = ['engine', 'view'].map(name => {
  const metadata = json(resolve(source(name), 'composer.json'));
  const version = metadata.extra.phpaml.version;
  const file = archive(source(name), `phpaml-${name}`, version);
  const {scripts, repositories, ...definition} = metadata;
  return {...definition, version, dist: {type: 'zip', url: pathToFileURL(file).href, shasum: sha(file, 'sha1')}};
});
const cliZip = archive(root, 'phpaml-cli', cliVersion);
const template = json(resolve(source('template'), 'phpaml.json'));
const templateZip = archive(source('template'), 'phpaml-template', template.version);
const frameworkZip = archive(source('framework'), 'phpaml-framework', template.runtime.framework);

const cache = resolve(work, 'cache');
for (const [family, version, zip] of [['templates', template.version, templateZip], ['framework', template.runtime.framework, frameworkZip]]) {
  const directory = resolve(cache, family, version); mkdirSync(directory, {recursive: true});
  const filename = family === 'templates' ? `phpaml-template-${version}.zip` : `phpaml-framework-${version}.zip`;
  cpSync(zip, resolve(directory, filename));
  writeFileSync(resolve(directory, `${filename}.sha256`), `${sha(zip)}  ${filename}\n`);
}

// Exercise the CLI files extracted from its source distribution in the same
// layout as the standalone packages. PHP/Composer executables can be bundled.
const cliExtract = resolve(work, 'cli-source'); extract(cliZip, cliExtract);
const cliSource = resolve(cliExtract, readdirSync(cliExtract)[0]);
const cliHome = resolve(work, 'cli'); mkdirSync(resolve(cliHome, 'runtime/bin'), {recursive: true});
for (const file of readdirSync(resolve(cliSource, 'cli')).filter(file => file.endsWith('.php'))) {
  cpSync(resolve(cliSource, 'cli', file), resolve(cliHome, 'runtime/bin', file));
}
cpSync(resolve(cliSource, 'phpaml.json'), resolve(cliHome, 'phpaml.json'));
mkdirSync(resolve(cliHome, 'runtime/composer'), {recursive: true});
const realComposer = process.env.COMPOSER_BINARY || run('which', ['composer']).trim();
cpSync(realComposer, resolve(cliHome, 'runtime/composer/composer.phar'));
const cli = resolve(cliHome, 'runtime/bin/aml.php');
const repositories = [{type: 'package', package: packages}];
if (process.env.PHPAML_EXTRA_PACKAGES_REPOSITORY) {
  repositories.push({type: 'package', package: json(process.env.PHPAML_EXTRA_PACKAGES_REPOSITORY)}, {'packagist.org': false});
}
const repositoryFile = resolve(work, 'repositories.json'); writeFileSync(repositoryFile, JSON.stringify(repositories));
// This adapter only supplies unpublished ZIPs. Every install/require command is
// executed by real Composer, including the starter's development dependencies.
const composer = resolve(work, 'composer-adapter.php');
const phpExecutable = run(php, ['-r', 'echo PHP_BINARY;']).trim();
writeFileSync(composer, `#!${phpExecutable}\n<?php
$path = getcwd() . '/composer.json';
$data = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
$data['repositories'] = json_decode(file_get_contents(getenv('PHPAML_TEST_REPOSITORIES')), true, 512, JSON_THROW_ON_ERROR);
file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\\n");
$command = array_merge([getenv('PHPAML_REAL_COMPOSER')], array_slice($argv, 1));
$process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes);
exit(is_resource($process) ? proc_close($process) : 1);
`, {mode: 0o755});
const env = {...process.env, AML_CACHE_HOME: cache, AML_COMPOSER_BINARY: composer,
  PHPAML_TEST_REPOSITORIES: repositoryFile, PHPAML_REAL_COMPOSER: realComposer,
  COMPOSER_HOME: resolve(work, 'composer-home'), COMPOSER_CACHE_DIR: resolve(work, 'composer-cache'), AML_LANG: 'en'};
if (process.env.PHPAML_COMPOSER_CACHE_SOURCE) {
  cpSync(process.env.PHPAML_COMPOSER_CACHE_SOURCE, env.COMPOSER_CACHE_DIR, {recursive: true});
}
const application = resolve(work, 'created-app');
step('cli-version', php, [cli, 'version'], {env});
step('create-view', php, [cli, 'create-view', application, '--offline'], {cwd: work, env});
const lock = json(resolve(application, 'composer.lock'));
for (const candidate of packages) {
  const installed = lock.packages.find(item => item.name === candidate.name);
  if (installed?.version !== candidate.version || installed.dist.url !== candidate.dist.url) throw new Error(`Wrong installed archive: ${candidate.name}`);
  const className = candidate.name === 'phpaml/engine' ? 'AML\\Engine\\EngineRuntime' : 'AML\\View\\FileApplication';
  const filename = step(`autoload-${candidate.name.split('/')[1]}`, php, ['-r', 'require $argv[1]; echo (new ReflectionClass($argv[2]))->getFileName();', resolve(application, 'runtime/autoload.php'), className], {env});
  if (!filename.startsWith(resolve(application, 'runtime', candidate.name) + '/')) throw new Error('Autoload escaped installed archive.');
}
step('app-tests', php, [cli, 'test'], {cwd: application, env});
step('app-doctor', php, [cli, 'doctor', '--offline', '--json'], {cwd: application, env});
step('app-build', php, [cli, 'build'], {cwd: application, env});
const production = resolve(work, 'production'); extract(resolve(application, 'output/phpaml-build.zip'), production);
const manifest = json(resolve(production, 'build-manifest.json'));
if (manifest.aml_version !== cliVersion) throw new Error('Build reports the wrong CLI version.');
for (const [file, hash] of Object.entries(manifest.hashes)) {
  if (sha(resolve(production, file)) !== hash) throw new Error(`Production checksum mismatch: ${file}`);
}
const engineAssets = resolve(production, 'runtime/phpaml/engine/assets');
const assets = json(resolve(engineAssets, 'public-assets.json'));
for (const file of assets) if (!existsSync(resolve(engineAssets, file))) throw new Error(`Production omitted Engine asset: ${file}`);
if (existsSync(resolve(production, '.env')) || existsSync(resolve(production, 'tests'))) throw new Error('Development files leaked into production.');
// Deployment settings belong to the destination, never inside the ZIP.
mkdirSync(resolve(production, 'runtime/storage/cache'), {recursive: true});
writeFileSync(resolve(production, '.env'), `APP_ENV=production\nAPP_DEBUG=false\nAPP_URL=https://phpaml-release.invalid\nAPP_KEY=${randomBytes(32).toString('hex')}\n`, {mode: 0o600});
step('production-doctor', php, [cli, 'doctor', '--offline', '--production', '--json'], {cwd: production, env});

const report = {status: 'prepared', cli: cliVersion, packages: packages.map(({name, version}) => ({name, version})), archives,
  application, production, logs, assetCount: assets.length, productionFiles: manifest.files.length, browsers: 'not-run'};
writeFileSync(reportFile, JSON.stringify(report, null, 2) + '\n');
writeFileSync(resolve(output, 'SHA256SUMS'), archives.map(({file, sha256}) => `${sha256}  ${file.slice(output.length + 1)}`).join('\n') + '\n');
if (!process.argv.includes('--prepare-only')) {
  const engine = source('engine');
  step('browsers', process.execPath, [resolve(engine, 'node_modules/playwright/cli.js'), 'test', `--config=${resolve(root, 'tests/frontend-release.config.mjs')}`], {
    cwd: root, env: {...env, PHPAML_ENGINE_SOURCE: engine, PHPAML_CREATED_APP: application, PHPAML_PRODUCTION_APP: production, PHPAML_FRONTEND_RESULTS: resolve(work, 'browsers')},
  });
  report.browsers = 'passed'; report.status = 'verified'; writeFileSync(reportFile, JSON.stringify(report, null, 2) + '\n');
}
console.log(`Verified archives and generated production application: ${reportFile}`);
