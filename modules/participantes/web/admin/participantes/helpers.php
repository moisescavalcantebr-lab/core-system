<?php
declare(strict_types=1);

function participantSetting(string $key, string $default = ''): string
{
    if (function_exists('getSetting')) {
        return getSetting($key, $default);
    }

    return $default;
}

function participantLabel(bool $plural = false): string
{
    return participantSetting($plural ? 'participant_label_plural' : 'participant_label', $plural ? 'Participantes' : 'Participante');
}

function participantContext(): string
{
    return participantSetting('participant_context', 'custom');
}

function participantRouteSlug(): string
{
    $slug = participantRouteNormalize(participantLabel(true));

    return $slug !== '' && !in_array($slug, ['participante', 'participantes'], true) ? $slug : 'participantes';
}

function participantRouteNormalize(string $value): string
{
    $value = trim($value);
    $normalized = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = $normalized !== false ? $normalized : $value;
    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

    return trim($value, '-');
}

function participantAdminUrl(string $path = 'index.php'): string
{
    return PROJECT_URL . '/admin/' . participantRouteSlug() . '/' . ltrim($path, '/');
}

function participantStatusLabel(string $status): string
{
    return match ($status) {
        'active' => 'Ativo',
        'inactive' => 'Inativo',
        'pending' => 'Pendente',
        default => 'Indefinido',
    };
}

function participantStatusBadge(string $status): string
{
    return match ($status) {
        'active' => 'c-badge--success',
        'inactive' => 'c-badge--danger',
        default => 'c-badge--warning',
    };
}

function participantDisplayName(?string $nickname, string $name): string
{
    $display = trim((string)$nickname);

    return $display !== '' ? $display : $name;
}

function participantInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $substr = function_exists('mb_substr') ? 'mb_substr' : 'substr';
    $upper = function_exists('mb_strtoupper') ? 'mb_strtoupper' : 'strtoupper';

    return $upper($substr($parts[0] ?? 'P', 0, 1) . $substr($parts[1] ?? '', 0, 1));
}

function participantAvatar(?string $avatar, string $name): string
{
    if ($avatar !== null && trim($avatar) !== '') {
        return '<img class="c-participant-avatar" src="' . htmlspecialchars(PROJECT_URL . '/' . ltrim($avatar, '/')) . '" alt="' . htmlspecialchars($name) . '">';
    }

    return '<span class="c-participant-avatar">' . htmlspecialchars(participantInitials($name)) . '</span>';
}

function participantValidateNickname(string $nickname): ?string
{
    if ($nickname === '') {
        return null;
    }

    if (function_exists('mb_strlen') ? mb_strlen($nickname) > 30 : strlen($nickname) > 30) {
        return 'Apelido deve ter no maximo 30 caracteres.';
    }

    return null;
}

function participantNormalizeBirthDate(?string $value): ?string
{
    $value = trim((string)$value);

    if ($value === '') {
        return null;
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        throw new InvalidArgumentException('Data de nascimento invalida.');
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();

    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        throw new InvalidArgumentException('Data de nascimento invalida.');
    }

    $minDate = new DateTimeImmutable('1900-01-01');
    $today = new DateTimeImmutable('today');

    if ($date < $minDate || $date > $today) {
        throw new InvalidArgumentException('Data de nascimento fora do intervalo permitido.');
    }

    return $date->format('Y-m-d');
}

function participantPublicRegistrationEnabled(): bool
{
    return participantSetting('participant_public_registration_enabled', '0') === '1';
}
