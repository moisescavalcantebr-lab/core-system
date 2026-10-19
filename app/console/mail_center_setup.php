<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
try{
    $options=getopt('',['google-json:','private-dir:','callback:','pilot-email:','apply']);
    $source=realpath((string)($options['google-json']??''));$dir=realpath((string)($options['private-dir']??''));$root=rtrim((string)realpath(dirname(__DIR__,2)),DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
    if(!$source||!is_file($source)||filesize($source)>65536||!$dir||!is_dir($dir)||!is_writable($dir)||str_starts_with($source,$root)||str_starts_with($dir.DIRECTORY_SEPARATOR,$root)||is_link($dir))throw new RuntimeException();
    $credentials=json_decode((string)file_get_contents($source),true,16,JSON_THROW_ON_ERROR)['web']??[];$callback=(string)($options['callback']??'');$email=strtolower((string)($options['pilot-email']??''));
    if(!in_array($callback,$credentials['redirect_uris']??[],true)||parse_url($callback,PHP_URL_SCHEME)!=='https'||parse_url($callback,PHP_URL_PATH)!=='/web/admin/central_contas/oauth_callback.php'||parse_url($callback,PHP_URL_QUERY)!==null||parse_url($callback,PHP_URL_FRAGMENT)!==null||parse_url($callback,PHP_URL_USER)!==null||!filter_var($email,FILTER_VALIDATE_EMAIL)||!preg_match('/@(gmail\.com|googlemail\.com)$/iD',$email)||empty($credentials['client_id'])||empty($credentials['client_secret']))throw new RuntimeException();
    if(is_file($dir.'/secrets.json'))throw new RuntimeException();
    if(!isset($options['apply'])){echo 'Simulação aprovada: JSON Web, callback HTTPS e diretório externo. Use --apply para gerar o arquivo privado; nenhum segredo será exibido.'.PHP_EOL;exit;}
    if(!is_dir($dir.'/attachments')&&!mkdir($dir.'/attachments',0700))throw new RuntimeException();
    $data=['active_key'=>'v1','keys'=>['v1'=>base64_encode(random_bytes(32))],'google'=>['client_id'=>$credentials['client_id'],'client_secret'=>$credentials['client_secret'],'redirect_uri'=>$callback,'pilot_email'=>$email],'attachments_dir'=>$dir.'/attachments'];
    $previous=umask(0077);try{$handle=fopen($dir.'/secrets.json','x');if(!$handle)throw new RuntimeException();if(fwrite($handle,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR))===false)throw new RuntimeException();fclose($handle);chmod($dir.'/secrets.json',0600);}finally{umask($previous);}
    echo 'Arquivo privado gerado fora do repositório. Ajuste a leitura para o usuário da aplicação e monte o arquivo somente para leitura. Guarde backup separado; nenhum segredo foi exibido.'.PHP_EOL;
}catch(Throwable $error){fwrite(STDERR,'Configuração não gerada: confira JSON Web, HTTPS, diretórios externos e ausência de secrets.json anterior. Não sobrescreva chaves existentes.'.PHP_EOL);exit(1);}
