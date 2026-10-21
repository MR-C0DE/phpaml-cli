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
const style = (selector, property, pseudo = null) => execute((selector, property, pseudo) => getComputedStyle(document.querySelector(selector), pseudo)[property], selector, property, pseudo);
const click = async selector => {
  const node = await execute(selector => document.querySelector(selector), selector);
  await execute(node => { node.scrollIntoView({behavior:'instant', block:'center'}); return node.getBoundingClientRect().toJSON(); }, node);
  await command(`/element/${node['element-6066-11e4-a52e-4f735466cecf']}/click`);
};
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
      assert.equal(await execute(() => document.querySelector('h1')?.textContent), 'Small pieces. Real possibilities.');
      assert.equal(await execute(() => document.querySelector('script[data-aml-engine]')?.type === 'module'), runtime === 'modules');
      await execute(() => { window.__releaseDocument = true; });
      const element = await execute(() => [...document.querySelectorAll('a')].find(node => node.textContent === 'Return home'));
      await execute(element => { element.scrollIntoView({behavior: 'instant', block: 'center'}); return element.getBoundingClientRect().toJSON(); }, element);
      await command(`/element/${element['element-6066-11e4-a52e-4f735466cecf']}/click`);
      await wait(() => document.querySelector('h1')?.textContent, 'Your next idea starts here.');
      assert.equal(await execute(() => window.__releaseDocument), true);
      assert.equal(await execute(() => getComputedStyle(document.querySelector('#app-shell')).display), 'flex');
      const projectName = await execute(() => document.getElementById('project-name'));
      const projectNameId = projectName['element-6066-11e4-a52e-4f735466cecf'];
      await command(`/element/${projectNameId}/clear`);
      await command(`/element/${projectNameId}/value`, {text: 'André <3'});
      await wait(() => document.getElementById('preview-name').textContent, 'André <3');
      await execute(() => {
        const picker = document.getElementById('project-accent');
        picker.value = '#126b56'; picker.dispatchEvent(new Event('change', {bubbles: true}));
      });
      await wait(() => getComputedStyle(document.getElementById('starter-preview')).backgroundColor, 'rgb(18, 107, 86)');
      await click('#project-rounded');
      await wait(() => getComputedStyle(document.getElementById('starter-preview')).borderRadius, '8px');
      const reset = await execute(() => [...document.querySelectorAll('button')].find(node => node.textContent === 'Reset preview'));
      await command(`/element/${reset['element-6066-11e4-a52e-4f735466cecf']}/click`);
      await wait(() => document.getElementById('preview-name').textContent, 'Something great');
      assert.equal(await execute(() => document.getElementById('project-rounded').checked), true);
      await wait(() => getComputedStyle(document.getElementById('starter-preview')).backgroundColor, 'rgb(109, 74, 255)');
      await command('/back'); await wait(() => document.querySelector('h1')?.textContent, 'Small pieces. Real possibilities.');
      await command('/forward'); await wait(() => document.querySelector('h1')?.textContent, 'Your next idea starts here.');
      await command('/url', {url: origin + '/controls'});
      await wait(() => !!document.querySelector('#style-example'), true);
      assert.equal(await execute(() => getComputedStyle(document.querySelector('#style-example')).paddingTop), '24px');
      await click('#style-change');
      await wait(() => getComputedStyle(document.querySelector('#style-greeting')).fontSize, '32px');
      assert.equal(await style('#style-title', 'color'), 'rgb(8, 127, 91)');
      await wait(() => !!document.querySelector('[data-aml-calendar-day="2028-02-29"]'), true);
      const day = await execute(() => document.querySelector('[data-aml-calendar-day="2028-02-29"]'));
      await command(`/element/${day['element-6066-11e4-a52e-4f735466cecf']}/click`);
      await wait(() => document.querySelector('#summary-date')?.textContent, 'Date : 2028-02-29');
      const submit = await execute(() => document.querySelector('button[type=submit]'));
      await command(`/element/${submit['element-6066-11e4-a52e-4f735466cecf']}/click`);
      await wait(() => document.querySelector('#confirmation')?.textContent, 'Rendez-vous confirmé pour le 2028-02-29.');
      assert.equal(await execute(() => getComputedStyle(document.querySelector('.controls-layout')).display), 'grid');
      await command('/url', {url: origin + '/style-check'});
      await wait(() => !!document.querySelector('[data-aml-engine-mounted="true"]'), true);
      await execute(() => {
        window.styleErrors = []; window.styleOriginalNode = document.getElementById('final-priority');
        document.addEventListener('aml:render-error', event => window.styleErrors.push(event.detail.error.message), true);
      });
      const sheets = await execute(() => document.querySelectorAll('style[data-aml-style]').length);
      assert.equal(await style('#final-priority', 'color'), 'rgb(255, 0, 0)');
      assert.equal(await style('#final-priority', 'width'), '140px');
      assert.equal(await style('#final-order', 'paddingLeft'), '33px');
      assert.equal(await style('#final-pseudo', 'color', '::before'), 'rgb(255, 0, 0)');
      assert.equal(await style('#final-literal', 'content', '::before'), '"$1 !important"');
      const initialColor = await style('#final-initial-reference', 'color');
      assert.equal(await style('#final-initial-binding', 'color'), initialColor);
      await click('#final-normal');
      await wait(() => getComputedStyle(document.querySelector('#final-priority')).width, '32px');
      assert.equal(await style('#final-priority', 'color'), 'rgb(0, 0, 255)');
      assert.equal(await style('#final-plain', 'color'), 'rgb(0, 128, 0)');
      assert.equal(await style('#final-default', 'paddingLeft'), '7px');
      assert.equal(await style('#final-order', 'paddingLeft'), '12px');
      await click('#final-important');
      await wait(() => getComputedStyle(document.querySelector('#final-priority')).width, '48px');
      assert.equal(await style('#final-priority', 'color'), 'rgb(0, 128, 0)');
      assert.equal(await style('#final-default', 'paddingLeft'), '10px');
      assert.equal(await style('#final-pseudo', 'color', '::before'), 'rgb(0, 128, 0)');
      assert.deepEqual(await execute(() => window.styleErrors), []);
      await click('#final-invalid');
      await wait(() => window.styleErrors.length > 0, true);
      const errorCount = await execute(() => window.styleErrors.length);
      assert.equal(await style('#final-priority', 'color'), 'rgb(0, 128, 0)');
      assert.equal(await style('#final-priority', 'width'), '64px');
      await click('#final-unrelated');
      assert.equal(await execute(() => window.styleErrors.length), errorCount);
      await click('#final-empty');
      await wait(() => getComputedStyle(document.querySelector('#final-priority')).width, '140px');
      assert.equal(await style('#final-priority', 'color'), 'rgb(0, 0, 255)');
      assert.equal(await style('#final-plain', 'color'), 'rgb(128, 0, 128)');
      assert.equal(await style('#final-order', 'paddingLeft'), '33px');
      await click('#final-reset');
      await wait(() => getComputedStyle(document.querySelector('#final-priority')).color, initialColor);
      assert.equal(await style('#final-pseudo', 'color', '::before'), initialColor);
      assert.equal(await style('#final-order', 'paddingLeft'), '0px');
      await click('#final-inherit');
      await wait(() => getComputedStyle(document.querySelector('#final-priority')).color, 'rgb(128, 0, 128)');
      assert.equal(await style('#final-pseudo', 'color', '::before'), 'rgb(128, 0, 128)');
      await click('#final-normal');
      await wait(() => getComputedStyle(document.querySelector('#final-plain')).color, 'rgb(0, 128, 0)');
      assert.equal(await execute(() => window.styleOriginalNode === document.getElementById('final-priority')), true);
      assert.equal(await execute(() => document.querySelectorAll('style[data-aml-style]').length), sheets);
      await command('/url', {url: origin + '/style-conditions'});
      await wait(() => !!document.querySelector('[data-aml-engine-mounted="true"]'), true);
      assert.equal(await style('#supports-yes', 'color'), 'rgb(112, 71, 235)');
      assert.equal(await style('#container-text', 'fontSize'), '16px');
      await wait(() => getComputedStyle(document.querySelector('#animated')).opacity, '0.6');
      await click('#change-conditions');
      await wait(() => getComputedStyle(document.querySelector('#container-text')).fontSize, '28px');
      assert.equal(await style('#supports-yes', 'color'), 'rgb(220, 38, 38)');
      assert.equal(await style('#pseudo-content', 'color', '::before'), 'rgb(220, 38, 38)');
      await wait(() => getComputedStyle(document.querySelector('#animated')).opacity, '0.8');
      assert.equal(await style('#other-animated', 'opacity'), '0.9');
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
