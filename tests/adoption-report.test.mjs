import test from 'node:test';
import assert from 'node:assert/strict';
import {assetPlatform, buildReport, classifyAsset, classifyOutcome, parseIssueForm} from '../scripts/adoption-report.mjs';

test('parses GitHub issue-form fields and classifies outcomes', () => {
  const fields = parseIssueForm('### Outcome\n\nInstallation and first project succeeded\n\n### Operating system\n\nmacOS ARM64');
  assert.equal(fields.Outcome, 'Installation and first project succeeded');
  assert.equal(fields['Operating system'], 'macOS ARM64');
  assert.equal(classifyOutcome(fields.Outcome), 'success');
  assert.equal(classifyOutcome('Project was created, but diagnostics failed'), 'diagnostic_failure');
});

test('separates installers, archives, checksums, and platforms', () => {
  assert.equal(classifyAsset('phpaml-windows-x64.exe'), 'installer');
  assert.equal(classifyAsset('aml-linux-x64.tar.gz'), 'portable');
  assert.equal(classifyAsset('aml-linux-x64.tar.gz.sha256'), 'checksum');
  assert.equal(assetPlatform('phpaml-macos-arm64.pkg'), 'macos');
});

test('builds aggregates without retaining issue authors or bodies', () => {
  const report = buildReport({
    capturedAt: '2026-08-24T00:00:00.000Z',
    repository: {stargazers_count: 2, forks_count: 1, subscribers_count: 1, open_issues_count: 1},
    release: {tag_name: 'v1', published_at: 'now', assets: [
      {name: 'phpaml-windows-x64.exe', download_count: 3},
      {name: 'phpaml-windows-x64.exe.sha256', download_count: 2},
    ]},
    issues: [{user: {login: 'private-in-output'}, body: '### Outcome\n\nInstallation failed\n\n### Operating system\n\nWindows 11 x64\n\n### Project type\n\nClassic MVC application'}],
  });
  assert.equal(report.release.downloads.installer, 3);
  assert.equal(report.activation.outcomes.installation_failure, 1);
  assert.equal(JSON.stringify(report).includes('private-in-output'), false);
});
