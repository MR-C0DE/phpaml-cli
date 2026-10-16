import {createRequire} from 'node:module';
import {resolve} from 'node:path';
import {readFileSync, mkdirSync, writeFileSync, rmSync} from 'node:fs';
import {createHash} from 'node:crypto';
const require = createRequire(resolve(process.env.PHPAML_ENGINE_SOURCE, 'package.json'));
const {test, expect} = require('@playwright/test');

test.use({trace: 'off'});

test('all distributed assets survive HTTP serving and production packaging', async ({request}, info) => {
  const assetsRoot = resolve(info.project.metadata.app, 'runtime/phpaml/engine/assets');
  const assets = JSON.parse(readFileSync(resolve(assetsRoot, 'public-assets.json'), 'utf8'));
  for (const asset of assets) {
    const response = await request.get('/_aml/' + asset);
    expect(response.status(), asset).toBe(200);
    expect(response.headers()['content-type'], asset).toContain(asset.endsWith('.map') ? 'application/json' : 'text/javascript');
    expect(response.headers()['cache-control'], asset).toMatch(/^public,/);
    const hash = buffer => createHash('sha256').update(buffer).digest('hex');
    expect(hash(await response.body()), asset).toBe(hash(readFileSync(resolve(assetsRoot, asset))));
  }
  const css = await request.get('/_aml/styles.css');
  expect(css.status()).toBe(200);
  expect(css.headers()['content-type']).toContain('text/css');
  expect(await css.text()).toContain('.view-app');
  const unknown = await request.get('/_aml/modules-unknown.min/missing.js');
  expect(unknown.status()).toBe(404);
  const missing = await request.get('/absent-route');
  expect(missing.status()).toBe(404);
  expect(await missing.text()).toContain('This page does not exist.');
  expect(missing.headers()['content-security-policy']).toContain("script-src 'self'");
  // A transient test page exercises the existing Error state through the real
  // generated bootstrap. Neither the starter nor the production ZIP is edited.
  const fixture = resolve(info.project.metadata.app, 'src/views/pages/release-error');
  mkdirSync(fixture);
  try {
    writeFileSync(resolve(fixture, 'page.php'), `<?php
namespace App\\Views\\Pages\\ReleaseError;
final class ReleaseErrorPage extends \\AML\\View\\Page {
  public function body(): \\AML\\View\\View { throw new \\RuntimeException('private-test-detail'); }
}
`);
    const failure = await request.get('/release-error');
    expect(failure.status()).toBe(500);
    const html = await failure.text();
    expect(html).toContain('Something went wrong.');
    expect(html).not.toContain('private-test-detail');
    expect(failure.headers()['content-security-policy']).toContain("script-src 'self'");
  } finally { rmSync(fixture, {recursive: true}); }
});
