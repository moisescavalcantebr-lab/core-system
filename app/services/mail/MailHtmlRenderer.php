<?php
declare(strict_types=1);

final class MailHtmlRenderer
{
    public static function render(string $html): string
    {
        if(strlen($html)>1024*1024)throw new DomainException('A mensagem é grande demais para a visualização HTML. Use o texto.');
        $config=HTMLPurifier_Config::createDefault();$config->set('Core.Encoding','UTF-8');$config->set('Cache.DefinitionImpl',null);
        $config->set('HTML.Allowed','p,br,strong,b,em,i,u,ul,ol,li,blockquote,pre,code,div,span,a[href|title],table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],h1,h2,h3,h4,hr');
        $config->set('URI.AllowedSchemes',['http'=>true,'https'=>true]);$config->set('URI.DisableExternalResources',true);$config->set('URI.DisableResources',true);
        return (new HTMLPurifier($config))->purify($html);
    }
    public static function parts(array $payload): array
    {
        $result=['text'=>'','html'=>'','attachments'=>[]];$count=0;
        $visit=function(array $part,int $depth=0)use(&$visit,&$result,&$count):void{
            if($depth>20||++$count>200)throw new DomainException('Estrutura da mensagem excede o limite.');
            $name=(string)($part['filename']??'');$type=strtolower((string)($part['mimeType']??''));
            if($name!==''){$result['attachments'][]=$part;return;}
            if(in_array($type,['text/plain','text/html'],true)&&isset($part['body']['data'])){
                $bytes=self::decode((string)$part['body']['data'],1024*1024);$field=$type==='text/plain'?'text':'html';
                $charset='UTF-8';foreach($part['headers']??[]as$header){if(strtolower((string)($header['name']??''))==='content-type'&&preg_match('/charset\s*=\s*["\']?([a-zA-Z0-9_-]+)/i',(string)($header['value']??''),$match)){foreach(mb_list_encodings()as$encoding){if(strcasecmp($match[1],$encoding)===0){$charset=$encoding;break;}}}}
                $bytes=mb_convert_encoding($bytes,'UTF-8',$charset);if(strlen($result[$field])+strlen($bytes)>1024*1024)throw new DomainException('Conteúdo da mensagem excede o limite.');$result[$field].=$bytes;
            }
            foreach($part['parts']??[]as$child){if(is_array($child))$visit($child,$depth+1);}
        };$visit($payload);return $result;
    }
    public static function decode(string $value,int $limit): string
    {
        if(strlen($value)>(int)ceil($limit*4/3)+8||!preg_match('/^[a-zA-Z0-9_=-]*$/D',$value))throw new DomainException('Conteúdo inválido ou acima do limite.');
        $decoded=base64_decode(strtr($value,'-_','+/'),true);if($decoded===false||strlen($decoded)>$limit)throw new DomainException('Conteúdo inválido ou acima do limite.');return $decoded;
    }
}
