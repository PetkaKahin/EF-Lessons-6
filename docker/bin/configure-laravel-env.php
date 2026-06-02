<?php

$path = $argv[1] ?? '.env';

if (! is_file($path)) {
    exit(0);
}

$values = [
    'DB_CONNECTION' => 'pgsql',
    'DB_HOST' => getenv('DB_HOST') ?: 'postgres',
    'DB_PORT' => getenv('DB_PORT') ?: '5432',
    'DB_DATABASE' => getenv('DB_DATABASE') ?: 'laravel',
    'DB_USERNAME' => getenv('DB_USERNAME') ?: 'laravel',
    'DB_PASSWORD' => getenv('DB_PASSWORD') ?: 'secret',
];

$lines = preg_split('/\R/', rtrim(file_get_contents($path), "\r\n"));
$seen = [];

foreach ($lines as $index => $line) {
    if ($line === '' || str_starts_with(ltrim($line), '#') || ! str_contains($line, '=')) {
        continue;
    }

    [$key] = explode('=', $line, 2);

    if (! array_key_exists($key, $values)) {
        continue;
    }

    $lines[$index] = $key.'='.formatEnvValue($values[$key]);
    $seen[$key] = true;
}

foreach ($values as $key => $value) {
    if (! isset($seen[$key])) {
        $lines[] = $key.'='.formatEnvValue($value);
    }
}

file_put_contents($path, implode(PHP_EOL, $lines).PHP_EOL);

function formatEnvValue(string $value): string
{
    if ($value === '') {
        return '""';
    }

    if (preg_match('/^[A-Za-z0-9_.:\/-]+$/', $value) === 1) {
        return $value;
    }

    return '"'.addcslashes($value, '\\"').'"';
}
