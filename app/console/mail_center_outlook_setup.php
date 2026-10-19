<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/services/mail/MailOutlookConfig.php';
require dirname(__DIR__).'/services/mail/MailTokenCipher.php';
try {
    $options=getopt('',['client-json:','private-dir:','callback:','email:','apply']);
    $root=strtolower(rtrim(str_replace('\\','/',(string)realpath(dirname(__DIR__,2))),'/').'/');
    $source=realpath((string)($options['client-json']??''));$directory=realpath((string)($options['private-dir']??''));
    foreach([$source,$directory]as$path)if(!$path||str_starts_with(strtolower(str_replace('\\','/',$path)).'/', $root)||is_link($path))throw new RuntimeException();
    if(!is_file($source)||filesize($source)>32768||!is_dir($directory)||!is_writable($directory)||!is_file($directory.'/secrets.json'))throw new RuntimeException();
    $client=json_decode((string)file_get_contents($source),true,16,JSON_THROW_ON_ERROR);
    $callback=(string)($options['callback']??'');$canonical=substr($callback,0,-strlen('/web/admin/central_contas/oauth_outlook_callback.php'));
    if(!filter_var($canonical,FILTER_VALIDATE_URL)||parse_url($canonical,PHP_URL_SCHEME)!=='https'||parse_url($canonical,PHP_URL_USER)!==null||parse_url($canonical,PHP_URL_QUERY)!==null||parse_url($canonical,PHP_URL_FRAGMENT)!==null||!empty(parse_url($canonical,PHP_URL_PATH)))throw new RuntimeException();
    $outlook=MailOutlookConfig::validate(['client_id'=>$client['client_id']??null,'client_secret'=>$client['client_secret']??null,'pilot_email'=>strtolower((string)($options['email']??'')),'redirect_uri'=>$callback],$canonical);
    unset($outlook['endpoints'],$outlook['provider']);
    $original=(string)file_get_contents($directory.'/secrets.json');$settings=json_decode($original,true,16,JSON_THROW_ON_ERROR);
    $originalOwner=fileowner($directory.'/secrets.json');$originalGroup=filegroup($directory.'/secrets.json');
    if(!is_int($originalOwner)||!is_int($originalGroup))throw new RuntimeException();
    if(isset($settings['outlook']))throw new RuntimeException();
    new MailTokenCipher($settings['keys'],$settings['active_key']);
    $settings['outlook']=$outlook;
    if(!isset($options['apply'])){echo 'Simulação aprovada: aplicativo Microsoft e caixa definidos; Gmail e chaves serão preservados.'.PHP_EOL;exit;}
    $previous=umask(0077);$temporary=$directory.'/secrets-outlook-'.bin2hex(random_bytes(8)).'.tmp';
    try {
        $backup=$directory.'/secrets-before-outlook-'.bin2hex(random_bytes(8)).'.json';$handle=fopen($backup,'x');if(!$handle)throw new RuntimeException();try{if(fwrite($handle,$original)!==strlen($original))throw new RuntimeException();}finally{fclose($handle);}chmod($backup,0600);
        $serialized=json_encode($settings,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$handle=fopen($temporary,'x');if(!$handle)throw new RuntimeException();try{if(fwrite($handle,$serialized)!==strlen($serialized))throw new RuntimeException();}finally{fclose($handle);}chmod($temporary,0600);
        if(fileowner($temporary)!==$originalOwner&&!chown($temporary,$originalOwner))throw new RuntimeException();
        if(filegroup($temporary)!==$originalGroup&&!chgrp($temporary,$originalGroup))throw new RuntimeException();
        if(!rename($temporary,$directory.'/secrets.json'))throw new RuntimeException();
    }finally{umask($previous);if(is_file($temporary))unlink($temporary);}
    echo 'Cliente Outlook adicionado ao arquivo privado; Gmail/chaves preservados e backup privado criado. Recrie o app para remontar o arquivo. Nenhuma conexão ou envio executado.'.PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'Configuração Outlook não concluída. Confira JSON externo, callback, arquivo privado existente e ausência de configuração Outlook anterior. Segredos omitidos.'.PHP_EOL);exit(1);}
