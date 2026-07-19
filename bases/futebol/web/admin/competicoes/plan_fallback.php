<?php
declare(strict_types=1);

if (!function_exists('projectPlanAllows')) {
    function projectPlanAllows(string $key, bool $default = false): bool
    {
        return $default;
    }
}

if (!function_exists('projectPlanLimit')) {
    function projectPlanLimit(string $key, int $default = 0): int
    {
        return $default;
    }
}

if (!function_exists('projectPlanList')) {
    function projectPlanList(string $key, array $default = []): array
    {
        return $default;
    }
}

if (!function_exists('projectPlanName')) {
    function projectPlanName(): string
    {
        return 'Plano Gratis';
    }
}
