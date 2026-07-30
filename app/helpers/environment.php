<?php

declare(strict_types=1);

function coreConfig(?string $key = null, $default = null)
{
    global $config;

    if (!is_array($config)) {
        return $default;
    }

    if ($key === null || $key === '') {
        return $config;
    }

    $value = $config;
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }

        $value = $value[$segment];
    }

    return $value;
}

function coreNormalizeEnvironment(string $environment): string
{
    $environment = strtolower(trim($environment));

    return match ($environment) {
        'prod', 'producao', 'production' => 'production',
        'lab', 'laboratorio', 'laboratory' => 'laboratory',
        'dev', 'development', 'local' => 'local',
        default => 'production',
    };
}

function coreEnvironment(): string
{
    $configured = coreConfig('app.environment');
    if (is_string($configured) && trim($configured) !== '') {
        return coreNormalizeEnvironment($configured);
    }

    $fromServer = getenv('CORE_ENV') ?: ($_SERVER['CORE_ENV'] ?? '');
    if (is_string($fromServer) && trim($fromServer) !== '') {
        return coreNormalizeEnvironment($fromServer);
    }

    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '' || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1') || str_contains($host, '::1')) {
        return 'local';
    }

    return 'production';
}

function coreEnvironmentLabel(): string
{
    return match (coreEnvironment()) {
        'local' => 'Local',
        'laboratory' => 'Laboratorio',
        default => 'Producao',
    };
}

function coreIsProduction(): bool
{
    return coreEnvironment() === 'production';
}

function coreIsLocal(): bool
{
    return coreEnvironment() === 'local';
}

function coreIsLaboratory(): bool
{
    return coreEnvironment() === 'laboratory' || coreEnvironment() === 'local';
}

function coreLaboratoryEnabled(): bool
{
    if (coreIsProduction()) {
        return (bool)coreConfig('app.allow_laboratory_in_production', false);
    }

    return (bool)coreConfig('app.laboratory_enabled', true);
}

function coreFeatureEnabled(string $key, bool $default = false): bool
{
    return (bool)coreConfig('features.' . $key, $default);
}

function coreRequireLaboratory(
    string $redirectTo = '/web/admin/bases/index.php',
    string $message = 'Acao disponivel apenas no laboratorio.'
): void {
    if (coreLaboratoryEnabled()) {
        return;
    }

    if (function_exists('flash')) {
        flash('error', $message);
    }

    if (function_exists('redirect')) {
        redirect($redirectTo);
    }

    http_response_code(403);
    exit($message);
}
