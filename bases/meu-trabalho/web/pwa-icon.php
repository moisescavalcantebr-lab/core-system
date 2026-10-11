<?php
declare(strict_types=1);

define('PROJECT_PATH', dirname(__DIR__));

$project = [];
$projectJson = PROJECT_PATH . '/project.json';
if (is_file($projectJson)) {
    $project = json_decode((string)file_get_contents($projectJson), true) ?: [];
}

$name = trim((string)($project['name'] ?? 'Projeto'));
$words = preg_split('/\s+/', $name) ?: [];
$initials = '';
foreach ($words as $word) {
    $initials .= mb_substr($word, 0, 1);
    if (mb_strlen($initials) >= 2) {
        break;
    }
}
$initials = mb_strtoupper($initials ?: 'MP');

header('Content-Type: image/svg+xml; charset=utf-8');
?>
<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
  <rect width="512" height="512" rx="104" fill="#050817"/>
  <rect x="44" y="44" width="424" height="424" rx="88" fill="#111827"/>
  <path d="M142 263l70 70 166-178" fill="none" stroke="#3b82f6" stroke-width="46" stroke-linecap="round" stroke-linejoin="round"/>
  <text x="256" y="287" text-anchor="middle" font-family="Arial, sans-serif" font-size="118" font-weight="800" fill="#ffffff"><?= htmlspecialchars($initials, ENT_XML1) ?></text>
</svg>

