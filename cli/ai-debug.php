<?php

declare(strict_types=1);

function aiConfigPath(): string
{
    $base = PHP_OS_FAMILY === 'Windows'
        ? (getenv('APPDATA') ?: getenv('USERPROFILE'))
        : (getenv('HOME') ?: sys_get_temp_dir());
    return rtrim((string) $base, '/\\') . '/.phpaml/ai.json';
}

/** @return array<string, string> */
function aiConfig(): array
{
    $path = aiConfigPath();
    $config = is_file($path) ? json_decode((string) file_get_contents($path), true) : [];
    return is_array($config) ? array_filter($config, 'is_string') : [];
}

function aiDefaultModel(string $provider): string
{
    return match ($provider) {
        'openai' => 'gpt-5-mini',
        'claude' => 'claude-sonnet-4-5',
        default => 'deepseek-v4-pro',
    };
}

function aiConfigure(string $provider, ?string $key, ?string $model): void
{
    $provider = strtolower($provider);
    if (!in_array($provider, ['deepseek', 'openai', 'claude'], true)) {
        fail('Fournisseur IA invalide. Utilisez deepseek, openai ou claude.');
    }
    if ($key === null && function_exists('stream_isatty') && stream_isatty(STDIN)) {
        fwrite(STDOUT, currentLanguage() === 'fr' ? 'Clé API (saisie masquée si possible) : ' : 'API key (hidden when possible): ');
        $key = trim((string) fgets(STDIN));
    }
    if ($key === null || trim($key) === '') {
        fail("Indiquez la clé avec --key ou la variable d'environnement du fournisseur.");
    }
    $path = aiConfigPath();
    if (!is_dir(dirname($path)) && !mkdir(dirname($path), 0700, true) && !is_dir(dirname($path))) {
        fail('Impossible de créer le dossier de configuration IA.');
    }
    $config = ['provider' => $provider, 'key' => trim($key), 'model' => $model ?: aiDefaultModel($provider)];
    if (file_put_contents($path, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL, LOCK_EX) === false) {
        fail('Impossible d’enregistrer la configuration IA.');
    }
    @chmod($path, 0600);
    output("✓ Agent IA configuré : {$provider} ({$config['model']})");
}

function aiKey(array $config): string
{
    $provider = $config['provider'] ?? 'deepseek';
    $environment = match ($provider) {
        'openai' => 'OPENAI_API_KEY',
        'claude' => 'ANTHROPIC_API_KEY',
        default => 'DEEPSEEK_API_KEY',
    };
    return (string) (getenv($environment) ?: ($config['key'] ?? ''));
}

/** @return array{provider:string,model:string,key:string} */
function aiResolvedConfig(): array
{
    $config = aiConfig();
    $provider = $config['provider'] ?? 'deepseek';
    $key = aiKey($config);
    if ($key === '') {
        fail("Agent IA non configuré. Exécutez 'aml ai:configure deepseek --key VOTRE_CLE'.");
    }
    return ['provider' => $provider, 'model' => $config['model'] ?? aiDefaultModel($provider), 'key' => $key];
}

function aiShow(): void
{
    $config = aiConfig();
    if ($config === []) {
        output(currentLanguage() === 'fr' ? 'Agent IA non configuré.' : 'AI agent is not configured.');
        return;
    }
    output('Provider : ' . ($config['provider'] ?? 'deepseek'));
    output('Model    : ' . ($config['model'] ?? aiDefaultModel($config['provider'] ?? 'deepseek')));
    output('API key  : ********');
    output('Config   : ' . aiConfigPath());
}

/** @return array{status:int,body:string} */
function aiHttp(string $url, array $headers, array $payload): array
{
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [...$headers, 'Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_USERAGENT => 'AML-Debug/1.0',
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if (!is_string($body)) {
        fail('Connexion IA impossible : ' . $error);
    }
    return ['status' => $status, 'body' => $body];
}

function aiRequest(array $config, string $system, string $prompt): string
{
    if ($config['provider'] === 'claude') {
        $response = aiHttp('https://api.anthropic.com/v1/messages', [
            'x-api-key: ' . $config['key'], 'anthropic-version: 2023-06-01',
        ], ['model' => $config['model'], 'max_tokens' => 4096, 'system' => $system, 'messages' => [['role' => 'user', 'content' => $prompt]]]);
        $json = json_decode($response['body'], true);
        $text = $json['content'][0]['text'] ?? null;
    } else {
        $url = $config['provider'] === 'openai'
            ? 'https://api.openai.com/v1/chat/completions'
            : 'https://api.deepseek.com/chat/completions';
        $response = aiHttp($url, ['Authorization: Bearer ' . $config['key']], [
            'model' => $config['model'],
            'messages' => [['role' => 'system', 'content' => $system], ['role' => 'user', 'content' => $prompt]],
            'response_format' => ['type' => 'json_object'],
            'temperature' => 0.1,
        ]);
        $json = json_decode($response['body'], true);
        $text = $json['choices'][0]['message']['content'] ?? null;
    }
    if ($response['status'] < 200 || $response['status'] >= 300 || !is_string($text)) {
        $error = is_array($json ?? null) ? ($json['error']['message'] ?? $response['body']) : $response['body'];
        fail("API IA (HTTP {$response['status']}) : {$error}");
    }
    return $text;
}

function aiCapture(string $command, string $root): string
{
    $descriptor = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $process = proc_open($command, $descriptor, $pipes, $root);
    if (!is_resource($process)) {
        return 'Unable to execute: ' . $command;
    }
    $content = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $code = proc_close($process);
    return "COMMAND: {$command}\nEXIT: {$code}\n" . substr($content, 0, 12000);
}

function aiProjectContext(string $root): string
{
    $sections = [aiCapture('aml doctor --json --offline', $root)];
    foreach (['info.json', 'composer.json', '.env.example', 'configs/app.php', 'public/index.php'] as $relative) {
        $path = $root . '/' . $relative;
        if (is_file($path) && filesize($path) <= 30000) {
            $sections[] = "FILE: {$relative}\n" . file_get_contents($path);
        }
    }
    $sections[] = aiCapture('find app configs public tests -maxdepth 3 -type f 2>/dev/null | sort | head -200', $root);
    return implode("\n\n---\n\n", $sections);
}

function aiSafeCommand(string $command): bool
{
    if (preg_match('/[;&|`$<>\\n\\r]/', $command)) {
        return false;
    }
    foreach (['aml doctor', 'aml install', 'aml test', 'aml routes', 'php -l ', 'composer validate'] as $allowed) {
        if ($command === $allowed || str_starts_with($command, $allowed . ' ') || str_starts_with($command, $allowed)) {
            return true;
        }
    }
    return false;
}

function aiDebug(bool $fix, bool $yes, ?string $problem): never
{
    $root = projectRoot();
    $config = aiResolvedConfig();
    output("AML AI Debugger — {$config['provider']} ({$config['model']})");
    output('Analyse du projet…');
    $system = <<<'PROMPT'
You are the PHPAML debugging agent. Diagnose a PHPAML project using the supplied evidence and official PHPAML conventions. Never request or reveal secrets. Return ONLY valid JSON with this schema:
{"diagnosis":"...","commands":["..."],"changes":[{"path":"relative/path","content":"complete replacement content","reason":"..."}],"verification":["aml doctor --offline","aml test"],"summary":"..."}
Commands are limited to: aml doctor, aml install, aml test, aml routes, php -l FILE, composer validate. Changes must stay inside the project, must not target .env, aml_env, .git, binaries, or secrets. Prefer the smallest correction. PHPAML current layout uses public/index.php, public/css/index.css, public/js/main.js, public/img, app, configs, and framework 0.2.0. Preserve application behavior and legacy URLs.
PROMPT;
    $prompt = "Reported problem: " . ($problem ?: 'Infer the problem from the diagnostics; the user did not provide details.')
        . "\nOfficial documentation: https://phpaml.com/docs and https://phpaml.com/fr/docs\n\nPROJECT EVIDENCE:\n"
        . aiProjectContext($root);
    $raw = aiRequest($config, $system, $prompt);
    $plan = json_decode($raw, true);
    if (!is_array($plan)) {
        fail('La réponse de l’agent IA n’est pas un plan JSON valide.');
    }
    output(); output('Diagnostic : ' . (string) ($plan['diagnosis'] ?? 'Non précisé'));
    $commands = array_values(array_filter(is_array($plan['commands'] ?? null) ? $plan['commands'] : [], 'is_string'));
    $changes = is_array($plan['changes'] ?? null) ? $plan['changes'] : [];
    if (!$fix) {
        foreach ($commands as $command) output('  → ' . $command);
        foreach ($changes as $change) if (is_array($change)) output('  ✎ ' . ($change['path'] ?? '?') . ' — ' . ($change['reason'] ?? ''));
        output(); output("Simulation terminée. Relancez 'aml debug --fix' pour appliquer les corrections sûres.");
        exit(0);
    }
    $backup = $root . '/aml_env/storage/debug-backups/' . date('Ymd-His');
    foreach ($changes as $change) {
        if (!is_array($change) || !is_string($change['path'] ?? null) || !is_string($change['content'] ?? null)) continue;
        $relative = ltrim($change['path'], '/\\');
        if ($relative === '' || str_contains($relative, '..') || preg_match('~^(\.env|\.git|aml_env)(/|$)~', $relative)) {
            output('⛔ Modification refusée : ' . $relative); continue;
        }
        if (!$yes) {
            fwrite(STDOUT, "Appliquer {$relative} ? [y/N] ");
            if (!in_array(strtolower(trim((string) fgets(STDIN))), ['y', 'yes', 'o', 'oui'], true)) continue;
        }
        $target = $root . '/' . $relative;
        if (is_file($target)) {
            @mkdir($backup . '/' . dirname($relative), 0775, true);
            copy($target, $backup . '/' . $relative);
        }
        @mkdir(dirname($target), 0775, true);
        file_put_contents($target, $change['content'], LOCK_EX);
        output('✓ Corrigé : ' . $relative);
    }
    foreach ([...$commands, ...(is_array($plan['verification'] ?? null) ? $plan['verification'] : [])] as $command) {
        if (!is_string($command) || !aiSafeCommand($command)) { output('⛔ Commande refusée : ' . (string) $command); continue; }
        output('→ ' . $command); output(aiCapture($command, $root));
    }
    output((string) ($plan['summary'] ?? 'Diagnostic IA terminé.'));
    exit(0);
}
