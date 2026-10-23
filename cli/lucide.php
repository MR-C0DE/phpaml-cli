<?php
declare(strict_types=1);

// Pin the upstream release and its npm SHA-512. Never execute package scripts.
const AML_LUCIDE_VERSION = '1.61.0';
const AML_LUCIDE_INTEGRITY = 'yLAiQB3MWUJEstsEDAwenSA3m7vr1tdLvgnC16Q/XMxRRma0DWwMs8M2bWlvgShrIs1m2mWMJ+0AiahqQO4GrA==';

function lucideGeometry(string $svg): array
{
    $svg = preg_replace('/<!--.*?-->/s', '', $svg);
    if (!preg_match('/^\s*<svg\b[^>]*>(.*?)<\/svg>\s*$/sD', $svg, $match)) throw new RuntimeException('Invalid Lucide SVG.');
    $body = $match[1]; $nodes = [];
    $shapes = ['path' => ['d'], 'circle' => ['cx', 'cy', 'r', 'fill'], 'ellipse' => ['cx', 'cy', 'rx', 'ry'], 'rect' => ['x', 'y', 'width', 'height', 'rx', 'ry'], 'line' => ['x1', 'y1', 'x2', 'y2'], 'polyline' => ['points'], 'polygon' => ['points']];
    while (trim($body) !== '') {
        if (!preg_match('/^\s*<([a-z]+)\b([^<>]*)\/\s*>/s', $body, $match) || !isset($shapes[$match[1]])) throw new RuntimeException('Unsafe Lucide SVG element.');
        $body = substr($body, strlen($match[0])); $tag = $match[1]; $attributes = []; $rest = $match[2];
        while (trim($rest) !== '') {
            if (!preg_match('/^\s+([a-z0-9-]+)="([^"]*)"/s', $rest, $attribute)) throw new RuntimeException('Invalid Lucide SVG attribute.');
            $rest = substr($rest, strlen($attribute[0])); [$unused, $key, $value] = $attribute;
            if (!in_array($key, $shapes[$tag], true) || isset($attributes[$key]) || strlen($value) > 16000
                || ($key === 'fill' ? $value !== 'currentColor' : !preg_match($key === 'd' ? '/^[MmZzLlHhVvCcSsQqTtAaEe0-9.,+\s-]+$/D' : '/^[Ee0-9.,+\s-]+$/D', $value))) throw new RuntimeException('Unsafe Lucide SVG attribute.');
            $attributes[$key] = $value;
        }
        if ($attributes === [] || count($nodes) >= 128) throw new RuntimeException('Invalid Lucide geometry.');
        $nodes[] = [$tag, $attributes];
    }
    if ($nodes === []) throw new RuntimeException('Empty Lucide SVG.');
    return $nodes;
}

/** Return inert JSON geometry and the unmodified upstream license. No archive extraction. */
function lucideReadArchive(string $path): array
{
    if (!hash_equals(AML_LUCIDE_INTEGRITY, base64_encode(hash_file('sha512', $path, true)))) throw new RuntimeException('Lucide archive integrity check failed.');
    $archive = new PharData($path);
    $package = json_decode($archive['package/package.json']->getContent(), true, 32, JSON_THROW_ON_ERROR);
    if (($package['name'] ?? null) !== 'lucide-static' || ($package['version'] ?? null) !== AML_LUCIDE_VERSION) throw new RuntimeException('Unexpected Lucide package.');
    $files = ['LICENSE' => $archive['package/LICENSE']->getContent()];
    foreach (new RecursiveIteratorIterator($archive) as $file) {
        if (!preg_match('#/package/icons/([a-z][a-z0-9]*(?:-[a-z0-9]+)*)\.svg$#D', str_replace('\\', '/', $file->getPathname()), $match)) continue;
        if ($file->isLink() || !$file->isFile() || $file->getSize() > 65536) throw new RuntimeException('Invalid Lucide archive entry.');
        $files[$match[1] . '.json'] = json_encode(lucideGeometry($file->getContent()), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    }
    if (count($files) < 2) throw new RuntimeException('Lucide catalogue is empty.');
    ksort($files);
    return $files;
}

function lucideRelativePath(string $version): string
{
    if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)) throw new RuntimeException('Invalid Lucide version.');
    return 'public/assets/phpaml/lucide/' . $version;
}

/** @return array<string, string> */
function lucideManagedFiles(string $root, string $version): array
{
    $relative = lucideRelativePath($version);
    $path = newProjectFilePath($root, $relative . '/catalog.json');
    if (!is_file($path)) return [];
    $data = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    if (($data['schema'] ?? null) !== 1 || ($data['version'] ?? null) !== $version || !is_array($data['files'] ?? null)) throw new RuntimeException('Invalid installed Lucide catalogue.');
    foreach ($data['files'] as $name => $hash) {
        if (!is_string($name) || ($name !== 'LICENSE' && !preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*\.json$/D', $name)) || $name === 'catalog.json'
            || !is_string($hash) || !preg_match('/^[a-f0-9]{64}$/D', $hash)) throw new RuntimeException('Invalid Lucide file ownership.');
    }
    return $data['files'];
}

/** Atomic installation of a completely validated catalogue; preserve existing modified files. */
function lucideWriteCatalogue(string $root, array $files): void
{
    $relative = lucideRelativePath(AML_LUCIDE_VERSION);
    $directory = newProjectFilePath($root, $relative);
    $managed = lucideManagedFiles($root, AML_LUCIDE_VERSION);
    // Existing files must either match the official archive or be absent (repair).
    foreach ($files as $name => $content) {
        if (!is_string($name) || ($name !== 'LICENSE' && !preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*\.json$/D', $name)) || $name === 'catalog.json' || !is_string($content)) throw new RuntimeException('Invalid Lucide asset filename.');
        $path = newProjectFilePath($root, $relative . '/' . $name);
        if (file_exists($path) && (!is_file($path) || !hash_equals(hash('sha256', $content), hash_file('sha256', $path)))) throw new RuntimeException('Modified file preserved: ' . $relative . '/' . $name);
    }
    if (!is_dir(dirname($directory)) && !mkdir(dirname($directory), 0755, true)) throw new RuntimeException('Cannot create Lucide asset directory.');
    $staging = newProjectFilePath($root, dirname($relative) . '/.lucide-' . bin2hex(random_bytes(8)));
    if (!mkdir($staging, 0755)) throw new RuntimeException('Cannot stage Lucide catalogue.');
    try {
        $hashes = [];
        foreach ($files as $name => $content) {
            if (file_put_contents($staging . '/' . $name, $content, LOCK_EX) !== strlen($content)) throw new RuntimeException('Cannot write Lucide asset.');
            $hashes[$name] = hash('sha256', $content);
        }
        $catalog = json_encode(['schema' => 1, 'version' => AML_LUCIDE_VERSION, 'integrity' => 'sha512-' . AML_LUCIDE_INTEGRITY, 'files' => $hashes], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        if (file_put_contents($staging . '/catalog.json', $catalog, LOCK_EX) !== strlen($catalog)) throw new RuntimeException('Cannot write Lucide catalogue.');
        if (!is_dir($directory)) {
            if (!rename($staging, $directory)) throw new RuntimeException('Cannot install Lucide catalogue.');
        } else {
            // Repair only missing managed assets; unknown files belong to the user.
            foreach ($files as $name => $_) {
                $path = newProjectFilePath($root, $relative . '/' . $name);
                if (!is_file($path) && !rename($staging . '/' . $name, $path)) throw new RuntimeException('Cannot repair Lucide asset.');
            }
            $catalogPath = newProjectFilePath($root, $relative . '/catalog.json');
            if (is_file($catalogPath) && $managed === []) throw new RuntimeException('Unmanaged Lucide catalogue preserved.');
            if (!rename($staging . '/catalog.json', $catalogPath)) throw new RuntimeException('Cannot update Lucide catalogue.');
        }
    } finally { if (is_dir($staging)) removeGeneratedPath($staging); }
}

function installLucide(string $root, bool $offline = false, ?string $version = null): void
{
    $manifest = projectInfo($root);
    $version ??= $manifest['modules']['lucide']['version'] ?? AML_LUCIDE_VERSION;
    if ($version !== AML_LUCIDE_VERSION) throw new RuntimeException('This CLI supports Lucide ' . AML_LUCIDE_VERSION . '; update AML for other catalogue versions.');
    newProjectFilePath($root, basename(projectManifestPath($root)));
    $cache = amlCacheRoot() . '/lucide-static-' . $version . '.tgz';
    if (is_file($cache) && !hash_equals(AML_LUCIDE_INTEGRITY, base64_encode(hash_file('sha512', $cache, true)))) throw new RuntimeException('Lucide cache integrity check failed. Remove the damaged archive and retry.');
    if (!is_file($cache)) {
        if ($offline) throw new RuntimeException('Lucide is absent from the local cache; run aml install lucide once with network access.');
        $bytes = httpGet('https://registry.npmjs.org/lucide-static/-/lucide-static-' . $version . '.tgz');
        if (!hash_equals(AML_LUCIDE_INTEGRITY, base64_encode(hash('sha512', $bytes, true)))) throw new RuntimeException('Lucide download integrity check failed.');
        if (!is_dir(dirname($cache)) && !mkdir(dirname($cache), 0755, true)) throw new RuntimeException('Cannot create Lucide cache.');
        $temporary = tempnam(dirname($cache), 'lucide-');
        if ($temporary === false) throw new RuntimeException('Cannot stage Lucide archive.');
        try {
            if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !rename($temporary, $cache)) throw new RuntimeException('Cannot cache Lucide archive.');
        } finally { if (is_file($temporary)) unlink($temporary); }
    }
    $files = lucideReadArchive($cache);
    lucideWriteCatalogue($root, $files);
    $manifest['modules']['lucide'] = array_replace($manifest['modules']['lucide'] ?? [], ['package' => 'lucide-static', 'version' => $version, 'integrity' => 'sha512-' . AML_LUCIDE_INTEGRITY]);
    writeProjectManifest($root, $manifest);
    output('Lucide ' . $version . ' installed: ' . (count($files) - 1) . ' local SVG icons.');
}

/** Conservative warning for direct, qualified and imported/aliased Icon calls. */
function lucideUsages(string $root): array
{
    $found = [];
    foreach (['src', 'app'] as $folder) {
        if (!is_dir($root . '/' . $folder) || is_link($root . '/' . $folder)) continue;
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $folder, FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isLink() || !$file->isFile() || strtolower($file->getExtension()) !== 'php') continue;
            foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
                if (is_array($token) && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && preg_match('/(?:^|\\\\)Icon$/iD', $token[1])) {
                    $found[] = substr($file->getPathname(), strlen($root) + 1); break;
                }
            }
        }
    }
    return $found;
}

function uninstallLucide(string $root, bool $force = false): void
{
    $manifest = projectInfo($root);
    $module = $manifest['modules']['lucide'] ?? null;
    if ($module === null) { output('Lucide is not installed.'); return; }
    $usages = lucideUsages($root);
    if ($usages !== []) {
        output('Icon references: ' . implode(', ', $usages));
        if (!$force) throw new RuntimeException('Remove these uses first, or run aml uninstall lucide --force. Dynamic usages may also exist.');
    }
    $version = $module['version'] ?? ''; $relative = lucideRelativePath($version);
    newProjectFilePath($root, basename(projectManifestPath($root)));
    $files = lucideManagedFiles($root, $version);
    // Check every path before any removal, including symlinks in directories.
    $paths = [];
    foreach ($files as $name => $hash) $paths[$name] = newProjectFilePath($root, $relative . '/' . $name);
    foreach ($paths as $name => $path) {
        if (!file_exists($path)) continue;
        if (!is_file($path) || !hash_equals($files[$name], hash_file('sha256', $path))) { output('Modified file preserved: ' . $relative . '/' . $name); continue; }
        if (!unlink($path)) throw new RuntimeException('Cannot remove Lucide asset: ' . $name);
    }
    $catalog = newProjectFilePath($root, $relative . '/catalog.json');
    if (is_file($catalog) && !unlink($catalog)) throw new RuntimeException('Cannot remove Lucide catalogue.');
    $directory = newProjectFilePath($root, $relative);
    if (is_dir($directory) && count(scandir($directory)) === 2) rmdir($directory);
    unset($manifest['modules']['lucide']);
    writeProjectManifest($root, $manifest);
    output('Lucide uninstalled. Custom and modified files are preserved.');
}
