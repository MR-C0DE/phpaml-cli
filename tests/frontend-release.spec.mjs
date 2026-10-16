import {createRequire} from 'node:module';
import {resolve} from 'node:path';
const require = createRequire(resolve(process.env.PHPAML_ENGINE_SOURCE, 'package.json'));
const {test, expect} = require('@playwright/test');

test('generated routes, styles, client navigation and runtime selection', async ({page}, info) => {
  const errors = [], failures = [], documents = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('requestfailed', request => {
    // History changes intentionally abort superseded navigation/prefetch work.
    const message = request.failure()?.errorText || '';
    if (!/aborted|cancelled|canceled/i.test(message)) failures.push(`${request.url()}: ${message}`);
  });
  page.on('response', response => { if (response.status() >= 500) failures.push(`${response.status()} ${response.url()}`); });
  page.on('request', request => { if (request.isNavigationRequest()) documents.push(request.url()); });
  await page.goto('/about');
  await expect(page.getByRole('heading', {name: 'A second page.'})).toBeVisible();
  await page.waitForFunction(() => typeof window.AMLEngine?.mount === 'function');
  const modular = info.project.metadata.runtime === 'modules';
  const script = page.locator('script[data-aml-engine]');
  await expect(script).toHaveAttribute('src', modular ? /\/modules-[^/]+\/engine.js$/ : /\/engine-[^/]+\.min.js$/);
  expect(await script.getAttribute('type')).toBe(modular ? 'module' : null);
  await page.evaluate(() => { window.__releaseDocument = 'same'; });
  await page.getByRole('link', {name: 'Return home'}).click();
  await expect(page.getByRole('heading', {name: 'Your PHPAML app is ready.'})).toBeVisible();
  await expect(page).toHaveTitle('My PHPAML application');
  expect(await page.evaluate(() => window.__releaseDocument)).toBe('same');
  expect(documents).toHaveLength(1);
  expect(await page.locator('.view-app').evaluate(element => getComputedStyle(element).display)).toBe('flex');
  await page.goBack();
  await expect(page.getByRole('heading', {name: 'A second page.'})).toBeVisible();
  await page.goForward();
  await expect(page.getByRole('heading', {name: 'Your PHPAML app is ready.'})).toBeVisible();
  expect(errors).toEqual([]);
  expect(failures).toEqual([]);
});

