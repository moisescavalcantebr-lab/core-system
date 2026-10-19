<?php
declare(strict_types=1);
class MailHttpClient
{
    public function request(string $method,string $url,array $headers=[],?string $body=null,bool $sending=false):array
    {
        $response=$this->fetch($method,$url,$headers,$body,$sending);if($response==='')return [];
        try{$data=json_decode($response,true,40,JSON_THROW_ON_ERROR|JSON_BIGINT_AS_STRING);}catch(Throwable $e){throw new MailProviderException($sending?'unknown':'provider');}
        if(!is_array($data))throw new MailProviderException($sending?'unknown':'provider');return $data;
    }
    public function bytes(string $url,array $headers=[]):string{return $this->fetch('GET',$url,$headers,null,false,2097152);}
    private function fetch(string $method,string $url,array $headers,?string $body,bool $sending,int $limit=12582912):string
    {
        $parts=parse_url($url);
        if(($parts['scheme']??'')!=='https'||!in_array($parts['host']??'',['gmail.googleapis.com','oauth2.googleapis.com','mail.zoho.com','mail.zoho.eu','accounts.zoho.com','accounts.zoho.eu','graph.microsoft.com'],true)
            ||isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||(isset($parts['port'])&&$parts['port']!==443)||!in_array($method,['GET','POST','PUT','DELETE'],true))throw new InvalidArgumentException('Destino de integração não permitido.');
        $ch=curl_init($url);$response='';$retry=0;
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>$sending?25:10,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$response,$limit):int{if(strlen($response)+strlen($chunk)>$limit)return 0;$response.=$chunk;return strlen($chunk);},
            CURLOPT_HEADERFUNCTION=>static function($handle,string $line)use(&$retry):int{if(preg_match('/^Retry-After:\s*(\d+)\s*$/i',trim($line),$m))$retry=min(3600,(int)$m[1]);return strlen($line);},
        ]);
        if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,$body);
        $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_errno($ch);curl_close($ch);
        if($ok===false||$error||$status===0)throw new MailProviderException($sending?'unknown':'network');
        if($status<200||$status>=300){
            if($status===403){$problem=json_decode($response,true);foreach($problem['error']['errors']??[]as$item)if(in_array($item['reason']??'',['rateLimitExceeded','userRateLimitExceeded','dailyLimitExceeded'],true))throw new MailProviderException('quota',$retry);}
            throw new MailProviderException(match(true){$status===401=>'auth',$status===429=>'quota',$status===404=>'missing',$sending&&$status>=500=>'unknown',default=>'provider'},$retry);
        }
        return $response;
    }
}
