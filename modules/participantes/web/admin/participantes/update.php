<?php
declare(strict_types=1);
require __DIR__.'/../../../app/bootstrap/project_bootstrap.php';
require __DIR__.'/helpers.php';
participantRequireAdmin();
if ($_SERVER['REQUEST_METHOD']!=='POST') { http_response_code(405); exit('Metodo invalido'); }
csrf_verify();
$id=(int)($_GET['id'] ?? 0);
if ($id<=0) { http_response_code(400); exit('Participante invalido.'); }
try {
    participantSave($pdo,$id,$_POST);
    flash('success',participantLabel().' atualizado.');
} catch (Throwable $e) {
    error_log('Participant update failed: '.$e->getMessage());
    flash('error',$e instanceof InvalidArgumentException || ($e instanceof RuntimeException && !$e instanceof PDOException)?$e->getMessage():'Nao foi possivel atualizar o registro agora.');
    $_SESSION['participant_form_input']=$_POST;
    $_SESSION['participant_form_input']['id']=$id;
    unset($_SESSION['participant_form_input']['password']);
    redirect(participantAdminUrl('edit.php?id='.$id));
}
redirect(participantAdminUrl());
