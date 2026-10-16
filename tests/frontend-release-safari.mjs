// macOS supplement to Playwright: verify the installed Safari, not WebKit.
// Requires Safari's already-enabled Allow Remote Automation setting.
import assert from 'node:assert/strict';
import {spawn} from 'node:child_process';
import {readFileSync, writeFileSync, openSync, closeSync} from 'node:fs';
import {resolve, dirname} from 'node:path';
import {fileURLToPath} from 'node:url';
const root = resolve(fileURLToPath(new URL('..', import.meta.url)));
const version = JSON.parse(readFileSync(resolve(root, 'phpaml.json'))).version;
const reportPath = process.env.PHPAML_RELEASE_REPORT || resolve(root, `dist/frontend-${version}/verification.json`);
const report = JSON.parse(readFileSync(reportPath));
const children = [], results = [];
const driver = `http://127.0.0.1:${process.env.PHPAML_SAFARI_PORT || 8179}`;
const delay = ms => new Promise(resolve => setTimeout(resolve, ms));
let session;
function start(command, args, env, name) {
  const fd = openSync(resolve(report.logs, `${name}.log`), 'w');
  const child = spawn(command, args, {cwd: root, env: {...process.env, ...env}, detached: true, stdio: ['ignore', fd, fd]});
  closeSync(fd); children.push(child);
  child.on('error', error => { child.startError = error; });
  return child;
}
async function ready(url, child) {
  for (let i = 0; i < 100; i++) {
    if (child.startError) throw child.startError;
    if (child.exitCode !== null) throw new Error(`${url} exited before becoming ready`);
    try { if ((await fetch(url, {signal: AbortSignal.timeout(1000)})).ok) return; } catch {}
    await delay(100);
  }
  throw new Error(`Server did not start: ${url}`);
}
async function request(method, path, body) {
  const response = await fetch(driver + path, {method, headers: {'Content-Type': 'application/json'},
    ...(body === undefined ? {} : {body: JSON.stringify(body)}), signal: AbortSignal.timeout(40_000)});
  const payload = await response.json();
  if (!response.ok || payload.value?.error) throw new Error(JSON.stringify(payload));
  return payload.value;
}
const command = (path, body = {}) => request('POST', `/session/${session}${path}`, body);
const execute = (fn, ...args) => command('/execute/sync', {script: `return (${fn})(...arguments)`, args});
async function wait(fn, expected) {
  let value;
  for (let i = 0; i < 150; i++) {
    value = await execute(fn);
    if (JSON.stringify(value) === JSON.stringify(expected)) return;
    await delay(100);
  }
  assert.deepEqual(value, expected);
}
try {
  const service = start('/usr/bin/safaridriver', ['-p', new URL(driver).port], {}, 'safari-driver');
  await ready(driver + '/status', service);
  const created = await request('POST', '/session', {capabilities: {alwaysMatch: {browserName: 'safari'}}});
  session = created.sessionId;
  await command('/timeouts', {implicit: 0, script: 15000, pageLoad: 30000});
  await command('/window/rect', {width: 1280, height: 900});
  let port = Number(process.env.PHPAML_SAFARI_APP_PORT || 8170);
  for (const [build, app] of [['development', report.application], ['production', report.production]]) {
    for (const runtime of ['modules', 'full']) {
      const origin = `http://127.0.0.1:${port++}`;
      const server = start(process.env.PHP_BINARY || 'php', ['-S', new URL(origin).host, '-t', resolve(app, 'public'), resolve(root, 'tests/frontend-release-router.php')],
        {PHP_CLI_SERVER_WORKERS: '2', PHPAML_FRONTEND_APP: app, PHPAML_BROWSER_RUNTIME: runtime}, `safari-${build}-${runtime}`);
      await ready(origin, server);
      await command('/url', {url: origin + '/about'});
      await wait(() => !!document.querySelector('[data-aml-engine-mounted="true"]'), true);
      assert.equal(await execute(() => document.querySelector('h1')?.textContent), 'A second page.');
      assert.equal(await execute(() => document.querySelector('script[data-aml-engine]')?.type === 'module'), runtime === 'modules');
      await execute(() => { window.__releaseDocument = true; });
      const element = await execute(() => [...document.querySelectorAll('a')].find(node => node.textContent === 'Return home'));
      await command('/execute/async', {script: "const done=arguments[arguments.length-1];arguments[0].scrollIntoView({behavior:'instant',block:'center'});requestAnimationFrame(()=>requestAnimationFrame(done));", args: [element]});
      await command(`/element/${element['element-6066-11e4-a52e-4f735466cecf']}/click`);
      await wait(() => document.querySelector('h1')?.textContent, 'Your PHPAML app is ready.');
      assert.equal(await execute(() => window.__releaseDocument), true);
      assert.equal(await execute(() => getComputedStyle(document.querySelector('.view-app')).display), 'flex');
      await command('/back'); await wait(() => document.querySelector('h1')?.textContent, 'A second page.');
      await command('/forward'); await wait(() => document.querySelector('h1')?.textContent, 'Your PHPAML app is ready.');
      await command('/url', {url: origin + '/absent-route'});
      await wait(() => document.querySelector('h1')?.textContent, 'This page does not exist.');
      results.push({build, runtime, status: 'passed'});
      console.log(`Safari ${created.capabilities.browserVersion}: ${build} / ${runtime} passed`);
    }
  }
  writeFileSync(resolve(dirname(reportPath), 'safari.json'), JSON.stringify({version: created.capabilities.browserVersion, results}, null, 2) + '\n');
} finally {
  if (session) await request('DELETE', `/session/${session}`).catch(() => {});
  for (const child of children) if (child.pid) { try { process.kill(-child.pid, 'SIGTERM'); } catch {} }
}
