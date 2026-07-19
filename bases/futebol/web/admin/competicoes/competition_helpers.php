<?php
declare(strict_types=1);

if (!function_exists('competitionIsDefaultFriendly')) {
    function competitionIsDefaultFriendly(array $competition): bool
    {
        return strtolower(trim((string)($competition['name'] ?? ''))) === 'amistoso'
            && (string)($competition['type'] ?? '') === 'friendly';
    }
}
