#!/usr/bin/env node

import { writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

const API = 'https://api.github.com';

export const parseIssueForm = (body = '') => {
  const fields = {};
  const pattern = /^###\s+(.+?)\s*\n+([\s\S]*?)(?=^###\s+|(?![\s\S]))/gm;
  for (const match of body.matchAll(pattern)) {
    fields[match[1].trim()] = match[2].trim().replace(/^_No response_$/i, '');
  }
  return fields;
};

export const classifyOutcome = (value = '') => {
  const outcome = value.toLowerCase();
  if (outcome.includes('first project succeeded')) return 'success';
  if (outcome.includes('project creation failed')) return 'creation_failure';
  if (outcome.includes('diagnostics failed')) return 'diagnostic_failure';
  if (outcome.includes('did not start')) return 'serve_failure';
  if (outcome.includes('installation failed')) return 'installation_failure';
  return 'unknown';
};

export const classifyAsset = (name) => {
  if (name.endsWith('.sha256')) return 'checksum';
  if (/\.(?:exe|pkg|deb)$/i.test(name)) return 'installer';
  if (/\.(?:zip|tar\.gz)$/i.test(name)) return 'portable';
  return 'other';
};

export const assetPlatform = (name) => {
  if (name.includes('windows')) return 'windows';
  if (name.includes('macos')) return 'macos';
  if (name.includes('linux')) return 'linux';
  return 'other';
};

const args = Object.fromEntries(process.argv.slice(2).map((argument) => {
  const [key, ...value] = argument.replace(/^--/, '').split('=');
  return [key, value.join('=') || true];
}));

const request = async (path) => {
  const headers = {
    Accept: 'application/vnd.github+json',
    'User-Agent': 'phpaml-adoption-report',
    'X-GitHub-Api-Version': '2022-11-28',
  };
  if (process.env.GITHUB_TOKEN) headers.Authorization = `Bearer ${process.env.GITHUB_TOKEN}`;
  const response = await fetch(`${API}${path}`, {headers});
  if (!response.ok) throw new Error(`GitHub API ${response.status}: ${path}`);
  return response.json();
};

const installationIssues = async (repo) => {
  const issues = [];
  for (let page = 1; ; page++) {
    const batch = await request(`/repos/${repo}/issues?state=all&labels=installation&per_page=100&page=${page}`);
    issues.push(...batch.filter((issue) => !issue.pull_request));
    if (batch.length < 100) return issues;
  }
};

export const buildReport = ({repository, release, issues, capturedAt}) => {
  const outcomes = {
    success: 0,
    installation_failure: 0,
    creation_failure: 0,
    diagnostic_failure: 0,
    serve_failure: 0,
    unknown: 0,
  };
  const platforms = {};
  const projectTypes = {};
  for (const issue of issues) {
    const fields = parseIssueForm(issue.body);
    outcomes[classifyOutcome(fields.Outcome)]++;
    const platform = fields['Operating system'] || 'Unknown';
    const project = fields['Project type'] || 'Unknown';
    platforms[platform] = (platforms[platform] || 0) + 1;
    projectTypes[project] = (projectTypes[project] || 0) + 1;
  }

  const downloads = {installer: 0, portable: 0, checksum: 0, other: 0, byPlatform: {windows: 0, macos: 0, linux: 0, other: 0}};
  for (const asset of release.assets) {
    const category = classifyAsset(asset.name);
    downloads[category] += asset.download_count;
    if (category === 'installer' || category === 'portable') {
      downloads.byPlatform[assetPlatform(asset.name)] += asset.download_count;
    }
  }

  return {
    capturedAt,
    privacy: 'Public aggregate metadata and voluntary reports only. No hidden CLI telemetry.',
    repository: {
      stars: repository.stargazers_count,
      forks: repository.forks_count,
      subscribers: repository.subscribers_count,
      openIssues: repository.open_issues_count,
    },
    release: {tag: release.tag_name, publishedAt: release.published_at, downloads},
    activation: {reports: issues.length, outcomes, platforms, projectTypes},
  };
};

const tableRows = (entries) => Object.entries(entries)
  .map(([name, value]) => `| ${name} | ${value} |`).join('\n');

export const toMarkdown = (report) => `# PHPAML adoption report

Captured: ${report.capturedAt}

> ${report.privacy}

## Repository

| Metric | Count |
| --- | ---: |
| Stars | ${report.repository.stars} |
| Forks | ${report.repository.forks} |
| Subscribers | ${report.repository.subscribers} |
| Open issues and pull requests | ${report.repository.openIssues} |

## Release ${report.release.tag}

| Download request | Count |
| --- | ---: |
| Installers | ${report.release.downloads.installer} |
| Portable archives | ${report.release.downloads.portable} |
| Checksums | ${report.release.downloads.checksum} |
| Other assets | ${report.release.downloads.other} |
| Windows binaries | ${report.release.downloads.byPlatform.windows} |
| macOS binaries | ${report.release.downloads.byPlatform.macos} |
| Linux binaries | ${report.release.downloads.byPlatform.linux} |

GitHub reports download requests, not unique users or successful installations.

## Voluntary first-project reports

| Outcome | Count |
| --- | ---: |
${tableRows(report.activation.outcomes)}

Total voluntary reports: ${report.activation.reports}.

These counts must not be interpreted as a representative success rate until the
sample is large enough and its voluntary-selection bias is stated.
`;

const main = async () => {
  const repo = String(args.repo || 'MR-C0DE/phpaml-cli');
  const tag = String(args.release || 'v1.7.0-beta.16');
  const [repository, release, issues] = await Promise.all([
    request(`/repos/${repo}`),
    request(`/repos/${repo}/releases/tags/${encodeURIComponent(tag)}`),
    installationIssues(repo),
  ]);
  const report = buildReport({repository, release, issues, capturedAt: new Date().toISOString()});
  const markdown = toMarkdown(report);
  if (args.json) await writeFile(String(args.json), `${JSON.stringify(report, null, 2)}\n`);
  if (args.markdown) await writeFile(String(args.markdown), markdown);
  if (!args.json && !args.markdown) process.stdout.write(markdown);
};

if (import.meta.url === pathToFileURL(process.argv[1]).href) {
  main().catch((error) => {
    console.error(error.message);
    process.exitCode = 1;
  });
}
