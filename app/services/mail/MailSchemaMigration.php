<?php
declare(strict_types=1);

final class MailSchemaMigration
{
    public const TABLES = ['core_mail_schema_versions', 'core_mail_providers', 'core_mail_categories', 'core_mail_accounts', 'core_mail_audit_events'];

    public function __construct(private PDO $pdo) {}

    public function migrateOrganization(bool $apply=false): string
    {
        $this->migratePilot($apply);
        $sql=(string)file_get_contents(dirname(__DIR__,2).'/database/mail_center_003_organization.sql');$checksum=hash('sha256',$sql);
        if($this->pdo->query("SHOW TABLES LIKE 'core_mail_schema_versions'")->fetchColumn()){
            $s=$this->pdo->prepare('SELECT checksum FROM core_mail_schema_versions WHERE version=?');$s->execute(['003_organization']);$stored=$s->fetchColumn();
            if($stored!==false){if(!hash_equals($checksum,(string)$stored))throw new RuntimeException('Checksum divergente na organização local.');return 'Migração 003 já aplicada; checksum conferido. '.$this->migrateReading($apply);}
        }
        if(!$apply)return 'Simulação: duas tabelas para marcações e preferências locais; nenhum provedor será alterado.';
        if((int)$this->pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':mail_center_migration'),5)")->fetchColumn()!==1)throw new RuntimeException('Migração em execução.');
        try{
            foreach(explode(';',$sql)as$statement)if(trim($statement)!=='')$this->pdo->exec($statement);
            $s=$this->pdo->prepare('INSERT INTO core_mail_schema_versions(version,checksum) VALUES(?,?) ON DUPLICATE KEY UPDATE checksum=checksum');$s->execute(['003_organization',$checksum]);
        }finally{$this->pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':mail_center_migration'))");}
        return 'Migração 003 aplicada. '.$this->migrateReading($apply);
    }

    private function migrateReading(bool $apply): string
    {
        $sql=(string)file_get_contents(dirname(__DIR__,2).'/database/mail_center_004_reading.sql');$checksum=hash('sha256',$sql);
        $s=$this->pdo->prepare('SELECT checksum FROM core_mail_schema_versions WHERE version=?');$s->execute(['004_reading']);$stored=$s->fetchColumn();
        if($stored!==false){if(!hash_equals($checksum,(string)$stored))throw new RuntimeException('Checksum divergente na preferência de leitura.');$this->pdo->query('SELECT reading_mode FROM core_mail_preferences LIMIT 0');return 'Migração 004 já aplicada; checksum conferido.';}
        if(!$apply)return 'Simulação: adicionar preferência local de leitura; nenhum dado alterado.';
        if((int)$this->pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':mail_center_migration'),5)")->fetchColumn()!==1)throw new RuntimeException('Migração em execução.');
        try{
            // Recover safely when the ALTER completed before recording its checksum.
            if(!$this->pdo->query("SHOW COLUMNS FROM core_mail_preferences LIKE 'reading_mode'")->fetchColumn())$this->pdo->exec($sql);
            $s=$this->pdo->prepare('INSERT INTO core_mail_schema_versions(version,checksum) VALUES(?,?) ON DUPLICATE KEY UPDATE checksum=checksum');$s->execute(['004_reading',$checksum]);
            return 'Migração 004 aplicada; preferência de leitura local.';
        }finally{$this->pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':mail_center_migration'))");}
    }

    public function migratePilot(bool $apply = false): string
    {
        $this->migrate($apply);
        $sql=(string)file_get_contents(dirname(__DIR__,2).'/database/mail_center_002_pilot.sql');
        $checksum=hash('sha256',$sql);
        $ledger=$this->pdo->query("SHOW TABLES LIKE 'core_mail_schema_versions'")->fetchColumn();
        if($ledger){
            $stmt=$this->pdo->prepare('SELECT checksum FROM core_mail_schema_versions WHERE version=?');$stmt->execute(['002_pilot']);$stored=$stmt->fetchColumn();
            if($stored!==false){if(!hash_equals($checksum,(string)$stored))throw new RuntimeException('Checksum divergente no piloto.');return 'Migração 002 já aplicada; checksum conferido.';}
        }
        if(!$apply)return 'Simulação: 9 tabelas adicionais para o piloto Gmail; nenhuma conexão ou mensagem real.';
        if((int)$this->pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':mail_center_migration'), 5)")->fetchColumn()!==1)throw new RuntimeException('Migração em execução.');
        try{
            foreach(explode(';',$sql)as$statement){if(trim($statement)!=='')$this->pdo->exec($statement);}
            $stmt=$this->pdo->prepare('INSERT INTO core_mail_schema_versions(version,checksum) VALUES(?,?) ON DUPLICATE KEY UPDATE checksum=checksum');$stmt->execute(['002_pilot',$checksum]);
            return 'Migração 002 aplicada; sem dados reais de caixas.';
        }finally{$this->pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':mail_center_migration'))");}
    }

    public function migrate(bool $apply = false): string
    {
        $path = dirname(__DIR__, 2) . '/database/mail_center_001_foundation.sql';
        $sql = (string)file_get_contents($path);
        $checksum = hash('sha256', $sql);
        foreach (['core_users', 'projects'] as $table) {
            $column = $this->pdo->query('SHOW COLUMNS FROM `' . $table . '` LIKE \'id\'')->fetch(PDO::FETCH_ASSOC);
            if (!$column || !preg_match('/^int(?:\(\d+\))? unsigned$/i', $column['Type'])) {
                throw new RuntimeException('Pré-requisito incompatível: ' . $table . '.id');
            }
        }
        $exists = $this->pdo->query("SHOW TABLES LIKE 'core_mail_schema_versions'")->fetchColumn();
        if ($exists) {
            $stmt = $this->pdo->prepare('SELECT checksum FROM core_mail_schema_versions WHERE version = ?');
            $stmt->execute(['001_foundation']);
            $stored = $stmt->fetchColumn();
            if ($stored !== false) {
                if (!hash_equals($checksum, (string)$stored)) {
                    throw new RuntimeException('Checksum divergente. Não altere migração aplicada.');
                }
                foreach (self::TABLES as $table) {
                    $this->pdo->query('SELECT 1 FROM `' . $table . '` LIMIT 0');
                }
                return 'Migração 001 já aplicada; checksum conferido.';
            }
        }
        if (!$apply) {
            return 'Simulação: criar/verificar 5 tabelas core_mail_* e cadastrar Gmail, Outlook e Zoho; nenhum dado alterado.';
        }
        $lock = (int)$this->pdo->query("SELECT GET_LOCK(CONCAT(DATABASE(), ':mail_center_migration'), 5)")->fetchColumn();
        if ($lock !== 1) {
            throw new RuntimeException('Outra migração está em execução.');
        }
        try {
            // DDL has implicit commits in MySQL. IF NOT EXISTS permits resuming partial application.
            foreach (explode(';', $sql) as $statement) {
                if (trim($statement) !== '') {
                    $this->pdo->exec($statement);
                }
            }
            $stmt = $this->pdo->prepare('INSERT INTO core_mail_schema_versions (version, checksum) VALUES (?, ?) ON DUPLICATE KEY UPDATE checksum = checksum');
            $stmt->execute(['001_foundation', $checksum]);
            return 'Migração 001 aplicada. Nenhuma tabela anterior modificada.';
        } finally {
            $this->pdo->query("SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':mail_center_migration'))");
        }
    }
}
