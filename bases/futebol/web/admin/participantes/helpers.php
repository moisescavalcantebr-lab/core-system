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

function participantOptionalAccess(): bool
{
    if (!defined('PROJECT_PATH')) return false;
    $path=PROJECT_PATH.'/modules/participantes/module.json';
    if (!is_file($path)) return false;
    $manifest=json_decode((string)file_get_contents($path),true);
    return is_array($manifest) && ($manifest['participant_access'] ?? 'required')==='optional';
}

function participantRequireAdmin(): void
{
    requireProjectAdmin();
    if ((projectUser()['status'] ?? '')!=='active') { http_response_code(403); exit('Acesso negado.'); }
}

function participantInputText(array $input,string $key): string
{
    $value=$input[$key] ?? '';
    if (!is_string($value)) throw new InvalidArgumentException('Campo invalido: '.$key.'.');
    return trim($value);
}

function participantSave(PDO $pdo,int $id,array $input): int
{
    $optional=participantOptionalAccess();
    $name=participantInputText($input,'name');
    $nickname=participantInputText($input,'nickname');
    $whatsapp=participantInputText($input,'whatsapp');
    $notes=participantInputText($input,'notes');
    $status=participantInputText($input,'status');
    if (!in_array($status,['active','inactive','pending'],true)) $status='active';
    $birthDate=participantNormalizeBirthDate(participantInputText($input,'birth_date'));
    if ($name==='') throw new InvalidArgumentException('Nome obrigatorio.');
    if (($error=participantValidateNickname($nickname))!==null) throw new InvalidArgumentException($error);
    $length=static fn(string $value): int=>function_exists('mb_strlen')?mb_strlen($value):strlen($value);
    if ($length($name)>150 || $length($whatsapp)>30) throw new InvalidArgumentException('Nome ou WhatsApp excede o limite permitido.');

    $pdo->beginTransaction();
    try {
        $participant=null;
        if ($id>0) {
            $stmt=$pdo->prepare('SELECT * FROM participants WHERE id=? FOR UPDATE');
            $stmt->execute([$id]); $participant=$stmt->fetch(PDO::FETCH_ASSOC);
            if (!$participant) throw new RuntimeException('Participante nao encontrado.');
        }
        $userId=(int)($participant['user_id'] ?? 0);
        if ($optional && $id>0) {
            $expected=$input['expected_user_id'] ?? null;
            if ((!is_int($expected) && (!is_string($expected) || !ctype_digit($expected))) || (int)$expected!==$userId) {
                throw new RuntimeException('O vinculo de acesso mudou. Atualize a pagina antes de salvar.');
            }
        }
        // Existing accounts cannot be detached by omitting or changing a form checkbox.
        $access=!$optional || $userId>0 || ($input['create_access'] ?? '')==='1';
        if ($access) {
            $accessStatus=null;
            $email=strtolower(participantInputText($input,'email'));
            $username=strtolower(participantInputText($input,'username'));
            $password=$input['password'] ?? '';
            if (!is_string($password)) throw new InvalidArgumentException('Senha invalida.');
            if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>150) throw new InvalidArgumentException('Informe um e-mail valido para o acesso.');
            if (!preg_match('/^[a-z0-9._-]{3,30}$/',$username)) throw new InvalidArgumentException('Usuario invalido. Use 3 a 30 caracteres, sem espacos.');
            if ($length($name)>100) throw new InvalidArgumentException('Nome do usuario deve ter no maximo 100 caracteres.');
            if (($userId===0 || $password!=='') && strlen($password)<4) throw new InvalidArgumentException('Senha obrigatoria com no minimo 4 caracteres.');
            if ($userId>0) {
                $stmt=$pdo->prepare('SELECT id,status FROM project_users WHERE id=? FOR UPDATE');
                $stmt->execute([$userId]);
                $account=$stmt->fetch(PDO::FETCH_ASSOC);
                if (!$account) throw new RuntimeException('Conta vinculada nao encontrada. Confira o cadastro antes de continuar.');
                if ($optional && array_key_exists('access_status',$input)) {
                    $chosen=participantInputText($input,'access_status');
                    $expected=participantInputText($input,'expected_access_status');
                    if (!in_array($chosen,['active','inactive','blocked'],true) || !in_array($expected,['active','inactive','blocked'],true)) throw new InvalidArgumentException('Estado de acesso invalido.');
                    if ($chosen!==$expected) {
                        if ($account['status']!==$expected) throw new RuntimeException('O estado de acesso mudou. Atualize a pagina antes de alterar.');
                        $accessStatus=$chosen;
                    }
                }
            }
            $stmt=$pdo->prepare('SELECT id FROM project_users WHERE (email=? OR username=?) AND id<>? LIMIT 1');
            $stmt->execute([$email,$username,$userId]);
            if ($stmt->fetchColumn()) throw new InvalidArgumentException('Ja existe outro usuario com este e-mail ou usuario.');
            if ($userId===0) {
                $stmt=$pdo->prepare("INSERT INTO project_users (name,email,username,password,must_change_password,role,status) VALUES (?,?,?,?,0,'CLIENT',?)");
                $stmt->execute([$name,$email,$username,password_hash($password,PASSWORD_DEFAULT),$optional?'active':($status==='active'?'active':'inactive')]);
                $userId=(int)$pdo->lastInsertId();
            } else {
                $sql='UPDATE project_users SET name=?,email=?,username=?';
                $params=[$name,$email,$username];
                if (!$optional) { $sql.=',status=?'; $params[]=$status==='active'?'active':'inactive'; }
                elseif ($accessStatus!==null) { $sql.=',status=?'; $params[]=$accessStatus; }
                if ($password!=='') { $sql.=',password=?,must_change_password=0'; $params[]=password_hash($password,PASSWORD_DEFAULT); }
                $sql.=' WHERE id=?'; $params[]=$userId;
                $pdo->prepare($sql)->execute($params);
            }
        }
        $params=[$userId?:null,$name,$nickname?:null,$whatsapp?:null,$birthDate,$status,$notes?:null];
        if ($id>0) {
            $params[]=$id;
            $pdo->prepare('UPDATE participants SET user_id=?,name=?,nickname=?,whatsapp=?,birth_date=?,status=?,notes=? WHERE id=?')->execute($params);
        } else {
            $pdo->prepare('INSERT INTO participants (user_id,name,nickname,whatsapp,birth_date,status,notes) VALUES (?,?,?,?,?,?,?)')->execute($params);
            $id=(int)$pdo->lastInsertId();
        }
        $pdo->commit();
        return $id;
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
}
