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
$viewRoot = getenv('PHPAML_VIEW_SOURCE') ?: $root . '/.integration/view';
$engineRoot = getenv('PHPAML_ENGINE_SOURCE') ?: $root . '/.integration/engine';
$view = $readJson($viewRoot . '/composer.json');
$engine = $readJson($engineRoot . '/composer.json');
$policy = (string) file_get_contents($root . '/VERSIONING.md');

$versions = [
    'AML CLI preview' => $cli['version'] ?? null,
    'PHPAML Template preview' => $template['version'] ?? null,
    'PHPAML Framework preview' => $template['runtime']['framework'] ?? null,
    'AML View preview' => $view['extra']['phpaml']['version'] ?? null,
    'PHPAML Engine preview' => $engine['extra']['phpaml']['version'] ?? null,
];

foreach ($versions as $label => $version) {
    if (!is_string($version) || $version === '' || !str_contains($policy, "- {$label}: `{$version}`")) {
        throw new RuntimeException("VERSIONING.md is not aligned with {$label}.");
    }
}

$source = (string) file_get_contents($root . '/cli/aml.php');
$viewVersion = $versions['AML View preview'];
$engineVersion = $versions['PHPAML Engine preview'];
if (!str_contains($source, "\$version === null ? '^{$viewVersion}'")
    || !str_contains($source, "escapeshellarg('phpaml/engine:^{$engineVersion}')")
    || ($view['require']['phpaml/engine'] ?? null) !== '^' . $engineVersion) {
    throw new RuntimeException('CLI and View must select the compatible frontend package versions.');
}

echo "Version policy matches CLI, Template, Framework, View and Engine; frontend constraints agree.\n";
