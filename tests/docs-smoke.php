<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$files = [$root . '/README.md', ...glob($root . '/docs/*.md') ?: []];
$errors = [];

foreach ($files as $file) {
    $content = file_get_contents($file);
    if (!is_string($content) || trim($content) === '') {
        $errors[] = basename($file) . ' est vide.';
        continue;
    }
    if (substr_count($content, '```') % 2 !== 0) {
        $errors[] = basename($file) . ' contient un bloc de code non fermé.';
    }
    preg_match_all('/\[[^]]+\]\(([^)]+)\)/', $content, $matches);
    foreach ($matches[1] as $target) {
        if (preg_match('~^(https?://|mailto:|#)~', $target)) {
            continue;
        }
        $path = explode('#', $target, 2)[0];
        if ($path !== '' && !is_file(dirname($file) . '/' . $path)) {
            $errors[] = basename($file) . " référence un fichier absent : {$target}";
        }
    }
}

if (count($files) < 10) {
    $errors[] = 'La documentation doit contenir une page d’accueil et au moins neuf guides.';
}

if ($errors !== []) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, count($files) . " pages de documentation vérifiées." . PHP_EOL);
