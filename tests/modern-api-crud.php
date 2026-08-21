<?php

declare(strict_types=1);

$cliRoot = dirname(__DIR__);
$buildRoot = dirname($cliRoot);
$frameworkSource = rtrim((string) (getenv('PHPAML_FRAMEWORK_SOURCE') ?: $buildRoot . '/phpaml-framework/src'), '/\\');
$dataSource = rtrim((string) (getenv('PHPAML_DATA_SOURCE') ?: $buildRoot . '/phpaml-data/src'), '/\\');
$project = sys_get_temp_dir() . '/phpaml-modern-api-' . bin2hex(random_bytes(6));

$remove = static function (string $root): void {
    if (!is_dir($root)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($root);
};

$expect = static function (bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
};

try {
    $expect(is_dir($frameworkSource) && is_dir($dataSource), 'Les sources Framework et Data sont requises pour ce test.');
    foreach (['public', 'runtime', 'src'] as $directory) mkdir($project . '/' . $directory, 0755, true);
    file_put_contents($project . '/public/index.php', "<?php\n");
    file_put_contents($project . '/phpaml.json', json_encode([
        'name' => 'modern-api-crud',
        'application' => ['type' => 'api', 'debug' => true, 'rate_limit' => ['enabled' => false]],
        'api' => ['enabled' => true, 'prefix' => '/api', 'auth' => ['enabled' => false]],
        'database' => ['dsn' => 'sqlite::memory:'],
        'data' => [
            'default' => 'main',
            'connections' => ['main' => ['driver' => 'sqlite', 'database' => 'runtime/storage/data.sqlite']],
            'migrations_path' => 'runtime/database/migrations',
            'models_path' => 'src/models',
            'seeders' => [],
        ],
        'modules' => ['data' => ['version' => 'test', 'driver' => 'sqlite']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    file_put_contents($project . '/composer.json', json_encode([
        'autoload' => ['psr-4' => ['App\\' => 'src/']],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . PHP_EOL);
    $autoload = <<<'PHP'
<?php
spl_autoload_register(static function (string $class): void {
    $prefixes = __PREFIXES__;
    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) continue;
        $file = $directory . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) require_once $file;
        return;
    }
});
PHP;
    $autoload = str_replace('__PREFIXES__', var_export([
        'PHPAML\\' => $frameworkSource,
        'AML\\Data\\' => $dataSource,
        'App\\' => $project . '/src',
    ], true), $autoload);
    file_put_contents($project . '/runtime/autoload.php', $autoload);

    $command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($cliRoot . '/cli/aml.php')
        . " make:api Movie --fields 'title:string,year:integer,genre:string?' --migration";
    passthru('cd ' . escapeshellarg($project) . ' && ' . $command, $exitCode);
    $expect($exitCode === 0, 'La génération de la ressource persistante a échoué.');
    $expect(!is_dir($project . '/configs'), 'Une API moderne ne doit pas créer configs/.');

    require $project . '/runtime/autoload.php';
    $config = PHPAML\Config\ApplicationConfig::load($project);
    $dataCommand = new AML\Data\Commands\DataCommand($project, $config['data']);
    $expect($dataCommand->run('data:migrate') === 0, 'La migration SQLite a échoué.');
    $application = new PHPAML\WebApplication($config);

    $response = $application->handle(new PHPAML\Http\Request('POST', '/api/v1/movies', [], ['title' => '', 'year' => 2024]));
    $expect($response->status() === 422, 'Un titre vide doit retourner 422.');

    $response = $application->handle(new PHPAML\Http\Request('POST', '/api/v1/movies', [], [
        'title' => 'Arrival', 'year' => 2016, 'genre' => 'Science-fiction',
    ]));
    $created = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
    $expect($response->status() === 201 && ($created['data']['id'] ?? null) === 1, 'La création doit retourner l’identité persistée.');

    $response = $application->handle(new PHPAML\Http\Request('GET', '/api/v1/movies'));
    $listed = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
    $expect($response->status() === 200 && ($listed['meta']['total'] ?? null) === 1, 'La collection doit contenir le film.');

    $response = $application->handle(new PHPAML\Http\Request('PATCH', '/api/v1/movies/1', [], ['year' => 2017]));
    $updated = json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
    $expect($response->status() === 200 && ($updated['data']['year'] ?? null) === 2017, 'La modification doit être persistée.');

    $response = $application->handle(new PHPAML\Http\Request('DELETE', '/api/v1/movies/1'));
    $expect($response->status() === 204, 'La suppression doit retourner 204.');
    $response = $application->handle(new PHPAML\Http\Request('GET', '/api/v1/movies/1'));
    $expect($response->status() === 404, 'La ressource supprimée doit retourner 404.');

    echo "modern API CRUD smoke: OK\n";
} finally {
    $remove($project);
}
