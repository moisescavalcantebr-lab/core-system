<?php
declare(strict_types=1);
// Never run against a regular Core environment.
if(PHP_SAPI!=='cli'||getenv('MAIL_CENTER_REHEARSAL')!=='isolated')exit(1);
ini_set('display_errors','0');
try{
    $pdo=new PDO('mysql:host=127.0.0.1;dbname=core;charset=utf8mb4','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    if($pdo->query('SELECT @@hostname')->fetchColumn()!=='mail-center-restore-test')throw new RuntimeException();
    $users=(int)$pdo->query('SELECT COUNT(*) FROM core_users')->fetchColumn();$projects=(int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn();
    $s=$pdo->prepare("SELECT COUNT(*) FROM core_users WHERE email=? AND status=1 AND role IN ('ADMIN','SUPER_ADMIN')");$s->execute(['moises.cavalcante@meuprojetoweb.com']);if((int)$s->fetchColumn()!==1)throw new RuntimeException();
    require dirname(__DIR__,2).'/app/services/mail/MailSchemaMigration.php';
    $migration=new MailSchemaMigration($pdo);$migration->migrateOrganization(false);$migration->migrateOrganization(true);$migration->migrateOrganization(true);
    if((int)$pdo->query('SELECT COUNT(*) FROM core_mail_schema_versions')->fetchColumn()!==4||(int)$pdo->query('SELECT COUNT(*) FROM core_users')->fetchColumn()!==$users||(int)$pdo->query('SELECT COUNT(*) FROM projects')->fetchColumn()!==$projects)throw new RuntimeException();
    echo 'PASS: backup restaurado, dados do Core preservados e migracoes 001-004 repetidas com sucesso em banco isolado, sem acesso aos provedores.'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Ensaio de restauracao/migracao falhou; detalhes privados omitidos.'.PHP_EOL);exit(1);}
