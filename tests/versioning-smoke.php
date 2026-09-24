<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$readJson = static function (string $path): array {
    $value = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($value)) throw new RuntimeException("Invalid manifest: {$path}");
    return $value;
};

$cli = $readJson($root . '/phpaml.json');
$templateRoot = getenv('PHPAML_TEMPLATE_SOURCE') ?: $root . '/.integration/template';
$template = $readJson($templateRoot . '/phpaml.json');
$policy = (string) file_get_contents($root . '/VERSIONING.md');

$versions = [
    'AML CLI preview' => $cli['version'] ?? null,
    'PHPAML Template preview' => $template['version'] ?? null,
    'PHPAML Framework preview' => $template['runtime']['framework'] ?? null,
];

foreach ($versions as $label => $version) {
    if (!is_string($version) || $version === '' || !str_contains($policy, "- {$label}: `{$version}`")) {
        throw new RuntimeException("VERSIONING.md is not aligned with {$label}.");
    }
}

echo "Version policy matches CLI, Template and Framework manifests.\n";
