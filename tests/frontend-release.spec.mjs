import {createRequire} from 'node:module';
import {resolve} from 'node:path';
import {pathToFileURL} from 'node:url';
const require = createRequire(resolve(process.env.PHPAML_ENGINE_SOURCE, 'package.json'));
const {test, expect} = require('@playwright/test');
const {verifyStyleFinalization} = await import(pathToFileURL(resolve(process.env.PHPAML_ENGINE_SOURCE, 'tests/browser/style-finalization-checks.mjs')));

test('generated routes, styles, client navigation and runtime selection', async ({page}, info) => {
  test.setTimeout(60_000);
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
  await page.goto('/controls');
  await expect(page.locator('#style-example')).toHaveCSS('padding-top', '24px');
  await page.locator('#style-change').click();
  await expect(page.locator('#style-greeting')).toHaveCSS('font-size', '32px');
  await expect(page.locator('#style-title')).toHaveCSS('color', 'rgb(8, 127, 91)');
  const styledButton = page.getByRole('button', {name: 'Activer le rappel', exact: true});
  await expect(styledButton).toHaveCSS('background-color', 'rgb(8, 127, 91)');
  await styledButton.hover();
  await expect(styledButton).toHaveCSS('background-color', 'rgb(86, 50, 189)');
  await expect(page.getByRole('grid')).toBeVisible();
  await page.locator('#country').selectOption('FR');
  await expect(page.locator('#summary-country')).toHaveText('Pays : FR');
  await page.getByRole('switch', {name: 'Recevoir un rappel'}).uncheck();
  await expect(page.locator('#summary-notifications')).toHaveText('Rappel désactivé');
  await page.locator('[data-aml-calendar-day="2028-02-29"]').click();
  await expect(page.locator('#summary-date')).toHaveText('Date : 2028-02-29');
  await page.getByRole('button', {name: 'Confirmer le rendez-vous'}).click();
  await expect(page.locator('#confirmation')).toHaveText('Rendez-vous confirmé pour le 2028-02-29.');
  expect(await page.locator('.controls-layout').evaluate(element => getComputedStyle(element).display)).toBe('grid');
  expect(errors).toEqual([]);
  expect(failures).toEqual([]);
});

test('generated styles preserve priority, clearing, conditions and animations from installed packages', async ({page}) => {
  await page.goto('/style-check');
  await expect(page.locator('[data-aml-engine-mounted="true"]')).toBeVisible();
  await verifyStyleFinalization(page, expect);
  await page.goto('/style-conditions');
  await expect(page.locator('#supports-yes')).toHaveCSS('color', 'rgb(112, 71, 235)');
  await expect(page.locator('#container-text')).toHaveCSS('font-size', '16px');
  await expect(page.locator('#animated')).toHaveCSS('opacity', '0.6');
  await page.locator('#change-conditions').click();
  await expect(page.locator('#supports-yes')).toHaveCSS('color', 'rgb(220, 38, 38)');
  await expect(page.locator('#container-text')).toHaveCSS('font-size', '28px');
  await expect(page.locator('#animated')).toHaveCSS('opacity', '0.8');
  await expect(page.locator('#other-animated')).toHaveCSS('opacity', '0.9');
  await expect.poll(() => page.locator('#pseudo-content').evaluate(node => getComputedStyle(node, '::before').color)).toBe('rgb(220, 38, 38)');
  await page.setViewportSize({width: 375, height: 812});
  await expect(page.locator('#media-range')).toHaveCSS('color', 'rgb(0, 0, 0)');
  await expect(page.locator('#container-text')).toHaveCSS('font-size', '28px');
});

