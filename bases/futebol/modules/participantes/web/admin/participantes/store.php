<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
participantRequireAdmin();
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('Metodo invalido'); }
csrf_verify();
try {
    participantSave($pdo,0,$_POST);
    flash('success',participantLabel().' cadastrado com sucesso.');
} catch (Throwable $e) {
    error_log('Participant create failed: '.$e->getMessage());
    flash('error',$e instanceof InvalidArgumentException || ($e instanceof RuntimeException && !$e instanceof PDOException)?$e->getMessage():'Nao foi possivel cadastrar o registro agora.');
    $_SESSION['participant_form_input']=$_POST;
    $_SESSION['participant_form_input']['id']=0;
    unset($_SESSION['participant_form_input']['password']);
    redirect(participantAdminUrl('create.php'));
}
redirect(participantAdminUrl());
