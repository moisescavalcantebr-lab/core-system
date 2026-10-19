<?php
declare(strict_types=1);

/** Adapts Zoho's account/folder API to the mailbox metadata used by the Core. */
final class ZohoMailProvider implements MailProviderInterface, MailMutableProviderInterface
{
    private ?string $accountId = null;
    private array $messages = [];
    private array $folderRows = [];
    private array $ownAddresses = [];
    private array $folderOverrides = [];
    private string $base;

    public function __construct(private MailHttpClient $http, string $region, private string $email, private ?Closure $folderLookup = null)
    {
        $this->base = MailZohoConfig::endpoints($region)['mail'];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Caixa Zoho inválida.');
    }

    public function capabilities(): array
    {
        return ['read'=>true, 'send'=>true, 'search'=>true, 'attachments'=>true, 'remote_modify'=>true, 'sync'=>'poll'];
    }

    private static function id(string $id): string
    {
        if (!preg_match('/^[0-9]{1,40}$/D', $id)) throw new InvalidArgumentException('Identificador Zoho inválido.');
        return $id;
    }

    private function request(string $token, string $path, array $query = [], ?array $body = null, bool $sending = false): array
    {
        $result = $this->http->request($body===null?'GET':'POST', $this->base.'/api/accounts'.$path.($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):''), ['Authorization: Zoho-oauthtoken '.$token,'Content-Type: application/json'], $body===null?null:json_encode($body, JSON_THROW_ON_ERROR), $sending);
        return self::data($result, $sending);
    }

    public static function data(array $result, bool $sending = false): array
    {
        $status = (int)($result['status']['code'] ?? 0);
        if ($status!==200) throw new MailProviderException(match(true) {
            in_array($status,[401,403],true)=>'auth', $status===429=>'quota', $status===404=>'missing', $sending&&($status===0||$status>=500)=>'unknown', default=>'provider',
        });
        if (!is_array($result['data']??null)) throw new MailProviderException($sending?'unknown':'provider');
        return $result['data'];
    }

    public function profile(string $accessToken): array
    {
        $accounts=$this->request($accessToken,'');
        $profile=self::mailboxIdentity($accounts,$this->email);
        $this->accountId=$profile['accountId'];
        $this->ownAddresses=[strtolower($this->email)=>true];
        foreach($accounts as$row){
            if(!is_array($row)||(string)($row['accountId']??'')!==$this->accountId)continue;
            if(is_string($row['primaryEmailAddress']??null)&&filter_var($row['primaryEmailAddress'],FILTER_VALIDATE_EMAIL))$this->ownAddresses[strtolower($row['primaryEmailAddress'])]=true;
            foreach(is_array($row['emailAddress']??null)?$row['emailAddress']:[]as$address){
                if(is_array($address)&&self::flag($address['isConfirmed']??null)===true&&is_string($address['mailId']??null)&&filter_var($address['mailId'],FILTER_VALIDATE_EMAIL))$this->ownAddresses[strtolower($address['mailId'])]=true;
            }
        }
        return $profile;
    }

    public function ownsAddress(string $email): bool
    {return isset($this->ownAddresses[strtolower(trim($email))]);}

    private static function flag(mixed $value): ?bool
    {
        return match($value){true,1,'1','true'=>true,false,0,'0','false'=>false,default=>null};
    }

    /** The provider's canonical primary address is authoritative, never the first alias. */
    public static function primaryIdentity(array $accounts, string $expectedEmail): array
    {
        $expectedEmail=strtolower(trim($expectedEmail));$matches=[];$alias=false;
        foreach($accounts as$row){
            if(!is_array($row)||($row['type']??'')!=='ZOHO_ACCOUNT'||self::flag($row['enabled']??null)!==true)continue;
            $hasCanonical=array_key_exists('primaryEmailAddress',$row);
            $canonical=is_string($row['primaryEmailAddress']??null)?strtolower(trim($row['primaryEmailAddress'])):'';
            $primary=$hasCanonical&&$canonical===$expectedEmail;$contradiction=false;
            foreach(is_array($row['emailAddress']??null)?$row['emailAddress']:[]as$address){
                if(!is_array($address)||!is_string($address['mailId']??null)||strtolower(trim($address['mailId']))!==$expectedEmail)continue;
                $isAlias=self::flag($address['isAlias']??null);$isPrimary=self::flag($address['isPrimary']??null);$confirmed=self::flag($address['isConfirmed']??null);
                if($isAlias===true||($hasCanonical&&$canonical!==$expectedEmail))$alias=true;
                if((array_key_exists('isAlias',$address)&&$isAlias!==false)||(array_key_exists('isPrimary',$address)&&$isPrimary!==true)||(array_key_exists('isConfirmed',$address)&&$confirmed!==true))$contradiction=true;
                if(!$hasCanonical&&$isPrimary===true&&$confirmed===true&&$isAlias===false)$primary=true;
            }
            if(!$primary||$contradiction)continue;
            $id=self::id((string)($row['accountId']??''));$matches[$id]=['emailAddress'=>$expectedEmail,'accountId'=>$id];
        }
        if(count($matches)===1)return array_values($matches)[0];
        if($alias)throw new DomainException('O endereço configurado aparece como alias no Zoho. Esta conexão exige o endereço principal da caixa; a conexão não foi salva.');
        throw new DomainException('O Zoho não confirmou uma única caixa principal ativa com o endereço configurado. Confira a conta selecionada; a conexão não foi salva.');
    }

    /** Accept only the configured address, with mailbox ownership and send permission. */
    public static function mailboxIdentity(array $accounts,string $expectedEmail): array
    {
        $expectedEmail=strtolower(trim($expectedEmail));$matches=[];$memberSeen=false;
        foreach($accounts as$row){
            if(!is_array($row)||($row['type']??'')!=='ZOHO_ACCOUNT'||self::flag($row['enabled']??null)!==true)continue;
            try{$primary=self::primaryIdentity([$row],$expectedEmail);$matches[$primary['accountId']]=$primary;continue;}catch(DomainException $e){}
            $canonical=is_string($row['primaryEmailAddress']??null)?strtolower(trim($row['primaryEmailAddress'])):'';
            if(!filter_var($canonical,FILTER_VALIDATE_EMAIL))continue;
            $member=false;
            foreach(is_array($row['emailAddress']??null)?$row['emailAddress']:[]as$address){
                if(!is_array($address)||!is_string($address['mailId']??null)||strtolower(trim($address['mailId']))!==$expectedEmail)continue;
                if(self::flag($address['isConfirmed']??null)!==true)continue;
                if(array_key_exists('isAlias',$address)&&self::flag($address['isAlias'])===null)continue;
                if(array_key_exists('isPrimary',$address)&&self::flag($address['isPrimary'])===null)continue;
                $member=true;
            }
            if(!$member)continue;
            $memberSeen=true;$canSend=false;
            foreach(is_array($row['sendMailDetails']??null)?$row['sendMailDetails']:[]as$sender){
                if(!is_array($sender)||!is_string($sender['fromAddress']??null)||strtolower(trim($sender['fromAddress']))!==$expectedEmail||self::flag($sender['status']??null)!==true)continue;
                // Unvalidated external SMTP identities are not mailbox aliases.
                if(($sender['mode']??'')==='extfrom')continue;
                if(($sender['mode']??'')==='mailbox'||self::flag($sender['validated']??null)===true||self::flag($sender['validationRequired']??null)===false)$canSend=true;
            }
            if(!$canSend)continue;
            $id=self::id((string)($row['accountId']??''));$matches[$id]=['emailAddress'=>$expectedEmail,'accountId'=>$id];
        }
        if(count($matches)===1)return array_values($matches)[0];
        if($memberSeen)throw new DomainException('O Zoho reconheceu o endereço escolhido, mas não confirmou uma única caixa com permissão de envio por ele. Confira esse remetente nas configurações do Zoho Mail; a conexão não foi salva.');
        throw new DomainException('O endereço configurado não foi confirmado como endereço principal ou alias verificado desta caixa Zoho. Um endereço apenas de login ou recuperação não é suficiente.');
    }

    private function account(string $token): string
    {
        if ($this->accountId===null) $this->profile($token);
        return '/'.$this->accountId;
    }

    public function folders(string $accessToken): array
    {
        $this->folderRows=$this->request($accessToken,$this->account($accessToken).'/folders');
        $labels=[];
        foreach ($this->folderRows as $row) $labels[]=['id'=>self::id((string)($row['folderId']??'')),'name'=>(string)($row['folderName']??'Pasta'),'type'=>(string)($row['folderType']??'')];
        return ['labels'=>$labels];
    }

    private function normalized(array $row): array
    {
        $id=self::id((string)($row['messageId']??''));
        $folder=self::id((string)($row['folderId']??''));
        $this->messages[$id]=$row;
        $headers=[];
        foreach (['subject'=>'Subject','fromAddress'=>'From','toAddress'=>'To','ccAddress'=>'Cc'] as $key=>$name) {
            $value=html_entity_decode((string)($row[$key]??''),ENT_QUOTES|ENT_HTML5,'UTF-8');
            if ($value==='Not Provided') $value='';
            $headers[]=['name'=>$name,'value'=>$value];
        }
        $labels=[$folder];
        if (in_array((string)($row['status']??''),['0','unread'],true)) $labels[]='UNREAD';
        foreach ($this->folderRows as $f) if ((string)($f['folderId']??'')===$folder && strtolower((string)($f['folderType']??''))==='sent') $labels[]='SENT';
        return ['id'=>$id,'threadId'=>(string)($row['threadId']??''),'internalDate'=>(string)($row['receivedTime']??$row['receivedtime']??$row['sentDateInGMT']??''),'labelIds'=>$labels,'hasAttachment'=>in_array((string)($row['hasAttachment']??''),['1','true'],true),'payload'=>['mimeType'=>'multipart/mixed','headers'=>$headers,'parts'=>[]]];
    }

    public function listMessages(string $accessToken, string $query = '', string $page = '', string $label = ''): array
    {
        if (strlen($query)>500 || ($page!==''&&!preg_match('/^[1-9][0-9]{0,5}$/D',$page))) throw new InvalidArgumentException('Busca Zoho inválida.');
        $start=$page===''?1:(int)$page;
        $cutoff=preg_match('/^after:([0-9]{1,12})$/D',$query,$m)?(int)$m[1]:0;
        $search=$query!==''&&!$cutoff;
        $params=['start'=>$start,'limit'=>10,'includeto'=>'true'];
        if ($search) {$params['searchKey']=$query;$params['receivedTime']=(string)(time()*1000);}
        // Zoho's archive flag selects archived mail in the live API; keep the
        // ordinary mailbox poll on received/sent mail rather than that view.
        else {$params+=['sortBy'=>'date','sortorder'=>'false','includesent'=>'true','includearchive'=>'false'];if($label!=='')$params['folderId']=self::id($label);}
        $rows=$this->request($accessToken,$this->account($accessToken).'/messages/'.($search?'search':'view'),$params);
        $ids=[];$old=false;
        foreach ($rows as $row) {
            $normalized=$this->normalized($row);
            if ($cutoff && (float)$normalized['internalDate']/1000<$cutoff) {$old=true;continue;}
            $ids[]=['id'=>$normalized['id']];
        }
        $result=['messages'=>$ids];
        if (count($rows)===10&&!$old&&$start<999990) $result['nextPageToken']=(string)($start+10);
        return $result;
    }

    private function folder(string $id): string
    {
        self::id($id);
        $folder=$this->folderOverrides[$id]??$this->messages[$id]['folderId']??($this->folderLookup!==null?($this->folderLookup)($id):null);
        if (!is_string($folder)&&!is_int($folder)) throw new MailProviderException('missing');
        return self::id((string)$folder);
    }

    /** Fresh metadata only: bypass cached folder membership, including archived mail. */
    public function messageInFolder(string $accessToken, string $id, string $folder): array
    {
        $id=self::id($id);$folder=self::id($folder);
        // A polling page may have cached this row before the remote change.
        unset($this->messages[$id]);
        $row=$this->request($accessToken,$this->account($accessToken).'/folders/'.$folder.'/messages/'.$id.'/details');
        if((string)($row['messageId']??'')!==$id||(string)($row['folderId']??'')!==$folder)throw new MailProviderException('provider');
        return $this->normalized($row);
    }

    public function message(string $accessToken, string $id, bool $full = false): array
    {
        $id=self::id($id);$folder=$this->folder($id);
        $path=$this->account($accessToken).'/folders/'.$folder.'/messages/'.$id;
        $row=!$full&&isset($this->messages[$id])?$this->messages[$id]:$this->request($accessToken,$path.'/details');
        if ((string)($row['messageId']??'')!==$id||(string)($row['folderId']??'')!==$folder) throw new MailProviderException('provider');
        $result=$this->normalized($row);
        if (!$full) return $result;
        $internetHeaders=$this->request($accessToken,$path.'/header',['raw'=>'false']);
        foreach($internetHeaders['headerContent']??[]as$name=>$values){
            if(strtolower((string)$name)!=='reply-to')continue;
            if(!is_array($values)||count($values)!==1||!is_string($values[0]))throw new DomainException('A mensagem não tem um destinatário de resposta inequívoco. Confira no Zoho.');
            $result['payload']['headers'][]=['name'=>'Reply-To','value'=>MailMessageService::clean($values[0],1024)];
        }
        $body=$this->request($accessToken,$path.'/content',['includeBlockContent'=>'true']);
        $html=(string)($body['content']??'');
        if (strlen($html)>1048576) throw new DomainException('Conteúdo acima do limite de leitura. Abra no Zoho.');
        $result['payload']['parts'][]=['partId'=>'0','mimeType'=>'text/html','body'=>['data'=>rtrim(strtr(base64_encode($html),'+/','-_'),'=')]];
        if ($result['hasAttachment']) {
            $info=$this->request($accessToken,$path.'/attachmentinfo',['includeInline'=>'false']);
            foreach (array_slice($info['attachments']??[],0,100) as $index=>$attachment) {
                $result['payload']['parts'][]=['partId'=>(string)($index+1),'mimeType'=>'application/octet-stream','filename'=>(string)($attachment['attachmentName']??'anexo'),'body'=>['size'=>(int)($attachment['attachmentSize']??0),'attachmentId'=>self::id((string)($attachment['attachmentId']??''))]];
            }
        }
        return $result;
    }

    public function history(string $accessToken, string $historyId, string $page = ''): array
    { throw new DomainException('O Zoho usa atualização por consulta; não oferece o histórico incremental do Gmail nesta integração.'); }

    public function attachment(string $accessToken, string $messageId, string $attachmentId): array
    {
        $url=$this->base.'/api/accounts'.$this->account($accessToken).'/folders/'.$this->folder($messageId).'/messages/'.self::id($messageId).'/attachments/'.self::id($attachmentId);
        $bytes=$this->http->bytes($url,['Authorization: Zoho-oauthtoken '.$accessToken]);
        return ['data'=>rtrim(strtr(base64_encode($bytes),'+/','-_'),'=')];
    }

    public function send(string $accessToken, string $raw, ?string $threadId = null): array
    { throw new DomainException('O Zoho exige composição estruturada, sem conversão implícita de MIME.'); }

    public function updateMessage(string $token,string $id,string $action,?string $destination=null,?string $source=null): array
    {
        self::id($id);$account=$this->account($token);$headers=['Authorization: Zoho-oauthtoken '.$token,'Content-Type: application/json'];
        if(!in_array($action,['read','unread','move','trash'],true))throw new InvalidArgumentException('Ação inválida.');
        if($action==='trash'){
            if(!$this->folderRows)$this->folders($token);$trash=[];
            foreach($this->folderRows as$f)if(strtolower((string)($f['folderType']??''))==='trash')$trash[]=self::id((string)$f['folderId']);
            if(count($trash)!==1)throw new DomainException('A pasta Lixeira não foi identificada no Zoho. Atualize a caixa.');
            if($this->folder($id)===$trash[0])throw new DomainException('Esta mensagem já está na lixeira.');
            $result=$this->http->request('DELETE',$this->base.'/api/accounts'.$account.'/folders/'.$this->folder($id).'/messages/'.$id.'?expunge=false',$headers,null,true);$destination=$trash[0];
        }else{
            $body=['mode'=>match($action){'read'=>'markAsRead','unread'=>'markAsUnread',default=>'moveMessage'},'messageId'=>[$id]];
            if($action==='move'){$body['destfolderId']=self::id($destination??'');$body['isArchive']=true;}
            $result=$this->http->request('PUT',$this->base.'/api/accounts'.$account.'/updatemessage',$headers,json_encode($body,JSON_THROW_ON_ERROR),true);
        }
        // Update responses may acknowledge success without a data object.
        if((int)($result['status']['code']??0)!==200)throw new MailProviderException('unknown');
        if($destination!==null)$this->folderOverrides[$id]=$destination;
        unset($this->messages[$id]);
        try{return $this->message($token,$id);}catch(Throwable $e){throw new MailProviderException('unknown');}
    }

    /** Only MailSendService supplies this immutable, verified snapshot. */
    public function sendSnapshot(string $token, array $snapshot): array
    {
        if (strtolower($snapshot['from']??'')!==strtolower($this->email)) throw new DomainException('Remetente diferente da caixa Zoho.');
        $account=$this->account($token);
        $body=['fromAddress'=>$snapshot['from'],'toAddress'=>implode(',',$snapshot['to']),'ccAddress'=>implode(',',$snapshot['cc']),'bccAddress'=>implode(',',$snapshot['bcc']),'subject'=>$snapshot['subject'],'content'=>$snapshot['body'],'mailFormat'=>'plaintext','encoding'=>'UTF-8','askReceipt'=>'no'];
        foreach ($snapshot['attachments']??[] as $file) {
            if (!is_string($file['bytes']??null)||strlen($file['bytes'])>2097152) throw new DomainException('Anexo Zoho inválido.');
            $response=$this->http->request('POST',$this->base.'/api/accounts'.$account.'/messages/attachments?'.http_build_query(['fileName'=>MailAttachmentService::filename($file['filename']),'isInline'=>'false']),['Authorization: Zoho-oauthtoken '.$token,'Content-Type: application/octet-stream'],$file['bytes']);
            $uploaded=self::data($response);$uploaded=array_is_list($uploaded)?($uploaded[0]??[]):$uploaded;
            $attachment=[];
            foreach (['storeName','attachmentName','attachmentPath'] as $field) {if(!is_scalar($uploaded[$field]??null)||strlen((string)$uploaded[$field])>1024)throw new MailProviderException('provider');$attachment[$field]=(string)$uploaded[$field];}
            $body['attachments'][]=$attachment;
        }
        $path=$account.'/messages';
        if (isset($snapshot['reply_headers']['remote_id'])) {$path.='/'.self::id($snapshot['reply_headers']['remote_id']);$body['action']='reply';}
        $data=$this->request($token,$path,[],$body,true);
        $id=(string)($data['messageId']??'');
        if (!preg_match('/^[0-9]{1,40}$/D',$id)) throw new MailProviderException('unknown');
        return ['id'=>$id];
    }

    public function confirmedSent(string $token, string $id): bool
    {
        self::id($id);$this->folders($token);
        foreach ($this->folderRows as $folder) if (strtolower((string)($folder['folderType']??''))==='sent') {
            $row=$this->request($token,$this->account($token).'/folders/'.self::id((string)$folder['folderId']).'/messages/'.$id.'/details');
            return (string)($row['messageId']??'')===$id&&(string)($row['folderId']??'')===(string)$folder['folderId'];
        }
        return false;
    }
}
