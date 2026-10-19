<?php
declare(strict_types=1);

require_once __DIR__ . '/MailAuditService.php';

final class MailAccountService
{
    public function __construct(private PDO $pdo, private int $owner)
    {
        $stmt = $pdo->prepare("SELECT id FROM core_users WHERE id = ? AND status = 1 AND role IN ('ADMIN', 'SUPER_ADMIN')");
        $stmt->execute([$owner]);
        if (!$stmt->fetchColumn()) {
            throw new OutOfBoundsException('Recurso indisponível.');
        }
    }

    public function providers(): array
    {
        return $this->pdo->query("SELECT id, code, name FROM core_mail_providers WHERE is_enabled = 1 AND code IN ('gmail','outlook','zoho') ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categories(): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM core_mail_categories WHERE owner_user_id = ? ORDER BY display_order, name');
        $stmt->execute([$this->owner]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function projects(): array
    {
        // These are Core-owned organizational references, never project DB credentials.
        return $this->pdo->query("SELECT id, name FROM projects WHERE status <> 'deleted' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function accounts(): array
    {
        $stmt = $this->pdo->prepare('SELECT a.*, p.name AS provider_name, p.code AS provider_code, c.name AS category_name, c.color AS category_color, j.name AS project_name FROM core_mail_accounts a INNER JOIN core_mail_providers p ON p.id = a.provider_id LEFT JOIN core_mail_categories c ON c.id = a.category_id AND c.owner_user_id = a.owner_user_id LEFT JOIN projects j ON j.id = a.project_id WHERE a.owner_user_id = ? ORDER BY a.is_active DESC, a.display_order, a.label, a.id');
        $stmt->execute([$this->owner]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM core_mail_accounts WHERE id = ? AND owner_user_id = ?');
        $stmt->execute([$id, $this->owner]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new OutOfBoundsException('Registro não encontrado.');
        }
        return $row;
    }

    private static function text(array $data, string $key, int $max, bool $required = false): string
    {
        if (isset($data[$key]) && !is_string($data[$key])) {
            throw new InvalidArgumentException('Campo inválido: ' . $key);
        }
        $value = trim((string)($data[$key] ?? ''));
        if (($required && $value === '') || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/u', $value)) {
            throw new InvalidArgumentException('Confira o campo ' . $key . ' (limite de ' . $max . ' caracteres).');
        }
        return $value;
    }

    private static function number(mixed $value, bool $positive = false): int
    {
        $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $positive ? 1 : 0, 'max_range' => 1000000000]]);
        if ($result === false) {
            throw new InvalidArgumentException('Número inválido.');
        }
        return $result;
    }

    private static function rejectUnexpected(array $data, array $allowed): void
    {
        if (array_diff(array_keys($data), $allowed)) {
            throw new InvalidArgumentException('O formulário contém campos não permitidos. Nenhuma senha deve ser informada.');
        }
    }

    private function atomic(callable $action): int
    {
        $this->pdo->beginTransaction();
        try {
            // Serializes owner mutations and makes the audit limit race-safe.
            $stmt = $this->pdo->prepare("SELECT id FROM core_users WHERE id = ? AND status = 1 AND role IN ('ADMIN', 'SUPER_ADMIN') FOR UPDATE");
            $stmt->execute([$this->owner]);
            if (!$stmt->fetchColumn()) {
                throw new OutOfBoundsException('Recurso indisponível.');
            }
            $limit = $this->pdo->prepare('SELECT COUNT(*) FROM core_mail_audit_events WHERE owner_user_id = ? AND created_at >= UTC_TIMESTAMP() - INTERVAL 1 MINUTE');
            $limit->execute([$this->owner]);
            if ((int)$limit->fetchColumn() >= 30) {
                throw new DomainException('Muitas alterações em sequência. Aguarde um minuto.');
            }
            $id = $action();
            $this->pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            if ($e instanceof PDOException && (int)($e->errorInfo[1] ?? 0) === 1062) {
                throw new DomainException('Já existe uma conta com esse e-mail ou uma função do e-mail com esse nome.');
            }
            throw $e;
        }
    }

    public function save(array $data): int
    {
        self::rejectUnexpected($data, ['_csrf','id','revision','provider_id','category_id','project_id','label','email','purpose','display_order']);
        $id = self::number($data['id'] ?? 0);
        $revision = self::number($data['revision'] ?? 0);
        $provider = self::number($data['provider_id'] ?? 0, true);
        $category = self::number(($data['category_id'] ?? 0) ?: 0);
        $project = self::number(($data['project_id'] ?? 0) ?: 0);
        $label = self::text($data, 'label', 120, true);
        $email = strtolower(self::text($data, 'email', 254, true));
        $purpose = self::text($data, 'purpose', 500);
        $order = self::number($data['display_order'] ?? 0);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Informe um endereço de e-mail válido.');
        }
        return $this->atomic(function () use ($id, $revision, $provider, $category, $project, $label, $email, $purpose, $order): int {
            if ($id) {
                $old = $this->find($id);
                if ((int)$old['revision'] !== $revision) {
                    throw new DomainException('A conta mudou. Atualize a página antes de editar.');
                }
                if($this->hasAuthorization($id)&&((int)$old['provider_id']!==$provider||strtolower($old['email'])!==$email))throw new DomainException('Desconecte a caixa antes de trocar o endereço ou o provedor.');
            }
            $stmt = $this->pdo->prepare("SELECT id FROM core_mail_providers WHERE id = ? AND is_enabled = 1 AND code IN ('gmail','outlook','zoho')");
            $stmt->execute([$provider]);
            if (!$stmt->fetchColumn()) {
                throw new InvalidArgumentException('Selecione Gmail, Outlook ou Zoho.');
            }
            if ($category) {
                $stmt = $this->pdo->prepare('SELECT id FROM core_mail_categories WHERE id = ? AND owner_user_id = ?');
                $stmt->execute([$category, $this->owner]);
                if (!$stmt->fetchColumn()) {
                    throw new InvalidArgumentException('Função do e-mail indisponível.');
                }
            }
            if ($project) {
                $stmt = $this->pdo->prepare("SELECT id FROM projects WHERE id = ? AND status <> 'deleted'");
                $stmt->execute([$project]);
                if (!$stmt->fetchColumn()) {
                    throw new InvalidArgumentException('Projeto indisponível.');
                }
            }
            if ($id) {
                $stmt = $this->pdo->prepare('UPDATE core_mail_accounts SET provider_id=?, category_id=?, project_id=?, label=?, email=?, purpose=?, display_order=?, revision=revision+1 WHERE id=? AND owner_user_id=? AND revision=?');
                $stmt->execute([$provider, $category ?: null, $project ?: null, $label, $email, $purpose, $order, $id, $this->owner, $revision]);
                if ($stmt->rowCount() !== 1) {
                    throw new DomainException('A conta mudou. Atualize a página.');
                }
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO core_mail_accounts (owner_user_id, provider_id, category_id, project_id, label, email, purpose, display_order) VALUES (?,?,?,?,?,?,?,?)');
                $stmt->execute([$this->owner, $provider, $category ?: null, $project ?: null, $label, $email, $purpose, $order]);
                $id = (int)$this->pdo->lastInsertId();
            }
            (new MailAuditService($this->pdo))->record($this->owner, $id, isset($old) ? 'account.updated' : 'account.created');
            return $id;
        });
    }

    public function setActive(int $id, int $revision, bool $active): int
    {
        return $this->atomic(function () use ($id, $revision, $active): int {
            $this->find($id);
            if(!$active&&$this->hasAuthorization($id))throw new DomainException('Desconecte a caixa antes de desativar o cadastro.');
            $stmt = $this->pdo->prepare('UPDATE core_mail_accounts SET is_active = ?, revision = revision+1 WHERE id = ? AND owner_user_id = ? AND revision = ?');
            $stmt->execute([(int)$active, $id, $this->owner, $revision]);
            if ($stmt->rowCount() !== 1) {
                throw new DomainException('A conta mudou. Atualize a página.');
            }
            (new MailAuditService($this->pdo))->record($this->owner, $id, $active ? 'account.enabled' : 'account.disabled');
            return $id;
        });
    }

    public function saveCategory(array $data): int
    {
        self::rejectUnexpected($data, ['_csrf','id','revision','name','color','display_order']);
        $id = self::number($data['id'] ?? 0);
        $revision = self::number($data['revision'] ?? 0);
        $name = self::text($data, 'name', 80, true);
        $color = self::text($data, 'color', 7, true);
        $order = self::number($data['display_order'] ?? 0);
        if (!preg_match('/^#[a-fA-F0-9]{6}$/D', $color)) {
            throw new InvalidArgumentException('Selecione uma cor válida.');
        }
        return $this->atomic(function () use ($id, $revision, $name, $color, $order): int {
            if ($id) {
                $stmt = $this->pdo->prepare('SELECT revision FROM core_mail_categories WHERE id = ? AND owner_user_id = ?');
                $stmt->execute([$id, $this->owner]);
                $current = $stmt->fetchColumn();
                if ($current === false) {
                    throw new OutOfBoundsException('Registro não encontrado.');
                }
                if ((int)$current !== $revision) {
                    throw new DomainException('A função do e-mail mudou. Atualize a página.');
                }
                $stmt = $this->pdo->prepare('UPDATE core_mail_categories SET name=?, color=?, display_order=?, revision=revision+1 WHERE id=? AND owner_user_id=? AND revision=?');
                $stmt->execute([$name, $color, $order, $id, $this->owner, $revision]);
            } else {
                $stmt = $this->pdo->prepare('INSERT INTO core_mail_categories (owner_user_id, name, color, display_order) VALUES (?,?,?,?)');
                $stmt->execute([$this->owner, $name, $color, $order]);
                $id = (int)$this->pdo->lastInsertId();
            }
            (new MailAuditService($this->pdo))->record($this->owner, null, $revision ? 'category.updated' : 'category.created');
            return $id;
        });
    }

    private function hasAuthorization(int $id): bool
    {
        if(!$this->pdo->query("SHOW TABLES LIKE 'core_mail_connections'")->fetchColumn())return false;
        $s=$this->pdo->prepare("SELECT 1 FROM core_mail_connections WHERE account_id=? AND owner_user_id=? AND status<>'disconnected' AND token_envelope IS NOT NULL");$s->execute([$id,$this->owner]);return (bool)$s->fetchColumn();
    }
}
