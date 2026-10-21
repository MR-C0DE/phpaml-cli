<?php
declare(strict_types=1);

define('PHPAML_CLI_LIBRARY_MODE', true);
$cli = $argv[1] ?? dirname(__DIR__) . '/cli/aml.php';
require $cli;
$reflection = new ReflectionFunction('resolveCliRelease');
$function = implode('', array_slice(file($cli), $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));
$version = json_decode(file_get_contents(dirname(__DIR__) . '/phpaml.json'), true, flags: JSON_THROW_ON_ERROR)['version'];
$checks = 0;
// Execute the actual resolver with HTTP/OS boundaries replaced, without making
// a release public or replacing the user's installed CLI.
foreach ([['Darwin', 'arm64', 'macos-arm64.pkg'], ['Linux', 'x86_64', 'linux-x64.deb'], ['Windows', 'AMD64', 'windows-x64.exe']] as [$os, $machine, $suffix]) {
    $namespace = 'AMLUpdateProbe' . $os;
    eval('namespace ' . $namespace . '; const PHP_OS_FAMILY = ' . var_export($os, true) . ';'
        . 'function php_uname($mode) { return ' . var_export($machine, true) . '; }'
        . 'function httpGet($url) { $GLOBALS["updateEndpoint"] = $url; return json_encode($GLOBALS["updateFixture"]); }'
        . 'function fail($message) { throw new \\RuntimeException($message); }' . $function);
    foreach ([[null, '1.6.0'], ['v' . $version, $version]] as [$requested, $selected]) {
        $package = "phpaml-{$selected}-{$suffix}";
        $GLOBALS['updateFixture'] = ['tag_name' => 'v' . $selected, 'assets' => [
            ['name' => $package, 'browser_download_url' => 'https://release.invalid/' . $package],
            ['name' => $package . '.sha256', 'browser_download_url' => 'https://release.invalid/' . $package . '.sha256'],
        ]];
        $resolve = $namespace . '\\resolveCliRelease'; $release = $resolve($requested);
        $endpoint = $requested === null ? '/releases/latest' : '/releases/tags/v' . $version;
        if (!str_ends_with($GLOBALS['updateEndpoint'], $endpoint) || $release['version'] !== $selected
            || $release['package_name'] !== $package || $release['checksum'] !== $release['package'] . '.sha256') throw new RuntimeException('Update channel or platform selection mismatch.');
        $checks++;
        array_pop($GLOBALS['updateFixture']['assets']);
        try { $resolve($requested); throw new LogicException('Missing checksum accepted.'); }
        catch (RuntimeException $error) { $checks++; }
    }
}
echo "$checks update channel/platform checks passed against the CLI resolver (mocked release metadata).\n";
