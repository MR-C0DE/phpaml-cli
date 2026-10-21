import {createRequire} from 'node:module';
import {resolve} from 'node:path';
const require = createRequire(resolve(process.env.PHPAML_ENGINE_SOURCE, 'package.json'));
const {defineConfig} = require('@playwright/test');
const quote = value => "'" + value.replaceAll("'", "'\\''") + "'";
const php = quote(process.env.PHP_BINARY || 'php');
const router = quote(resolve('tests/frontend-release-router.php'));
const projects = [], webServer = [];
let port = Number(process.env.PHPAML_FRONTEND_PORT || 8150);
for (const [build, app] of [['development', process.env.PHPAML_CREATED_APP], ['production', process.env.PHPAML_PRODUCTION_APP]]) {
  for (const runtime of ['modules', 'full']) {
    const baseURL = `http://127.0.0.1:${port++}`;
    webServer.push({command: `${php} -S ${new URL(baseURL).host} -t ${quote(resolve(app, 'public'))} ${router}`,
      env: {PHP_CLI_SERVER_WORKERS: '2', PHPAML_FRONTEND_APP: app, PHPAML_BROWSER_RUNTIME: runtime}, url: baseURL, reuseExistingServer: false});
    for (const browserName of ['chromium', 'firefox', 'webkit']) {
      projects.push({name: `${build}-${runtime}-${browserName}`, metadata: {build, runtime, app},
        // HTTP byte checks are browser-independent; run them once per integration.
        ...(browserName === 'chromium' ? {} : {grep: /generated (?:routes|styles)/}), use: {baseURL, browserName,
        ...(browserName === 'chromium' && process.env.CHROMIUM_EXECUTABLE_PATH ? {launchOptions: {executablePath: process.env.CHROMIUM_EXECUTABLE_PATH}} : {})}});
    }
  }
}
export default defineConfig({testDir: '.', testMatch: ['frontend-release.spec.mjs', 'frontend-release-assets.spec.mjs'], projects, webServer,
  workers: 1, timeout: 30_000, forbidOnly: true, outputDir: process.env.PHPAML_FRONTEND_RESULTS,
  reporter: [['list'], ['json', {outputFile: resolve(process.env.PHPAML_FRONTEND_RESULTS, 'report.json')}]], use: {trace: 'retain-on-failure'}});
