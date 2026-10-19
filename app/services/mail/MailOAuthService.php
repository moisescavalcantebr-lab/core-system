<?php
declare(strict_types=1);

use League\OAuth2\Client\Provider\GenericProvider;

class MailOAuthService
{
    public const SCOPES=['https://www.googleapis.com/auth/gmail.readonly','https://www.googleapis.com/auth/gmail.send'];
    public function __construct(private MailMessageService $store, private MailProviderInterface $api, private array $google, private MailHttpClient $http, private ?Closure $providerFactory = null) {}
    private function isZoho(): bool { return ($this->google['provider']??'gmail')==='zoho'; }
    private function isOutlook(): bool { return ($this->google['provider']??'gmail')==='outlook'; }
    private function scopes(): array
    {
        if(!$this->isZoho()&&!$this->isOutlook()&&($this->google['remote_organization_enabled']??false)===true&&($this->google['gmail_permanent_delete_enabled']??false)===true)return ['https://mail.google.com/'];
        $base=$this->isOutlook()?MailOutlookConfig::SCOPES:($this->isZoho()?MailZohoConfig::SCOPES:self::SCOPES);
        return ($this->google['remote_organization_enabled']??false)===true?array_values(array_unique(array_merge($base,MailRemoteMessageService::scopes($this->google['provider']??'gmail')))):$base;
    }
    private function endpoint(string $operation): string
    {
        if($this->isZoho())return MailZohoConfig::endpoints($this->google['region'])['accounts'].'/oauth/v2/'.$operation;
        if($this->isOutlook())return 'https://login.microsoftonline.com/consumers/oauth2/v2.0/'.($operation==='auth'?'authorize':'token');
        return $operation==='auth'?'https://accounts.google.com/o/oauth2/v2/auth':($operation==='token'?'https://oauth2.googleapis.com/token':'https://oauth2.googleapis.com/revoke');
    }
    private function account(int $id,bool $active=true): array
    {
        $row=$this->store->account($id,$active);
        if($row['provider_code']!==($this->google['provider']??'gmail'))throw new OutOfBoundsException('Provedor diferente da conexão solicitada.');
        return $row;
    }
    private function revoke(string $token): bool
    {
        // Microsoft has no per-refresh-token revocation endpoint for this flow.
        // Delete locally; the owner can also remove consent in account settings.
        if($this->isOutlook())return false;
        try{
            $result=$this->http->request('POST',$this->endpoint($this->isZoho()?'token/revoke':'revoke'),['Content-Type: application/x-www-form-urlencoded'],http_build_query(['token'=>$token]));
            return !$this->isZoho()||($result['status']??null)==='success';
        }catch(Throwable $e){return false;}
    }
    private function provider(): GenericProvider
    {
        if($this->providerFactory!==null)return ($this->providerFactory)();
        return new GenericProvider(['clientId'=>$this->google['client_id'],'clientSecret'=>$this->google['client_secret'],'redirectUri'=>$this->google['redirect_uri'],
            'urlAuthorize'=>$this->endpoint('auth'),'urlAccessToken'=>$this->endpoint('token'),'urlResourceOwnerDetails'=>$this->isOutlook()?'https://graph.microsoft.com/v1.0/me':($this->isZoho()?MailZohoConfig::endpoints($this->google['region'])['mail'].'/api/accounts':'https://gmail.googleapis.com/gmail/v1/users/me/profile'),
            'scopes'=>$this->scopes(),'scopeSeparator'=>$this->isZoho()?',':' ','pkceMethod'=>GenericProvider::PKCE_METHOD_S256],['httpClient'=>new GuzzleHttp\Client(['timeout'=>10,'connect_timeout'=>5,'allow_redirects'=>false,'verify'=>true])]);
    }
    public function begin(int $account, array &$session): string
    {
        $row=$this->account($account);if(strtolower($row['email'])!==$this->google['pilot_email'])throw new DomainException('Use o endereço aprovado para esta conexão.');
        $s=$this->store->pdo->prepare("SELECT COUNT(*) FROM core_mail_audit_events WHERE owner_user_id=? AND action='oauth.started' AND created_at>=UTC_TIMESTAMP()-INTERVAL 10 MINUTE");$s->execute([$this->store->owner]);if((int)$s->fetchColumn()>=5)throw new DomainException('Aguarde antes de iniciar outra conexão.');
        // GenericProvider adds the legacy approval_prompt by default. Null omits
        // it from the query so Google receives only the supported prompt option.
        $options=['access_type'=>'offline','prompt'=>'consent','approval_prompt'=>null];
        if($this->isOutlook())$options=['prompt'=>'consent','approval_prompt'=>null,'login_hint'=>$this->google['pilot_email']];
        elseif(!$this->isZoho())$options+=['include_granted_scopes'=>'false','login_hint'=>$this->google['pilot_email']];
        $provider=$this->provider();$url=$provider->getAuthorizationUrl($options);
        $session['mail_oauth']=['state'=>$provider->getState(),'pkce'=>$provider->getPkceCode(),'account'=>$account,'owner'=>$this->store->owner,'provider'=>$row['provider_code'],'expires'=>time()+600,'redirect'=>$this->google['redirect_uri'],'account_revision'=>(int)$row['revision']];
        $this->store->audit($account,'oauth.started');return $url;
    }
    public static function consume(array &$session, array $query, int $owner, string $redirect, string $provider = 'gmail'): array
    {
        $pending=$session['mail_oauth']??null;unset($session['mail_oauth']);
        if(!is_array($pending)||($pending['provider']??'gmail')!==$provider||!is_string($query['state']??null)||!is_string($pending['state']??null)||!hash_equals($pending['state'],$query['state'])||($pending['owner']??0)!==$owner||($pending['expires']??0)<time()||($pending['redirect']??'')!==$redirect)throw new DomainException('A conexão expirou ou não pertence a esta sessão. Inicie novamente.');
        if(isset($query['error']))throw new DomainException('A autorização não foi concluída no provedor.');
        if(!is_string($query['code']??null)||strlen($query['code'])<5||strlen($query['code'])>4096)throw new DomainException('Código de autorização inválido.');
        return $pending;
    }
    public function complete(array $pending, string $code): void
    {
        $account=(int)$pending['account'];$row=$this->account($account);$provider=$this->provider();$provider->setPkceCode($pending['pkce']);
        try{$token=$provider->getAccessToken('authorization_code',['code'=>$code]);}catch(Throwable $e){throw new MailProviderException('auth');}
        $values=$token->getValues();$scopes=preg_split('/[\s,]+/',(string)($values['scope']??''),-1,PREG_SPLIT_NO_EMPTY);
        if($this->isOutlook())$scopes=array_map(static fn($scope)=>str_starts_with($scope,'https://graph.microsoft.com/')?substr($scope,28):$scope,$scopes);
        $expected=$this->isOutlook()?['User.Read','Mail.Read','Mail.Send']:$this->scopes();
        // offline_access requests a refresh token; it need not be echoed as a
        // resource permission by Microsoft's token endpoint.
        $resourceScopes=$this->isOutlook()?array_values(array_diff($scopes,['offline_access'])):$scopes;
        if(array_diff($expected,$resourceScopes)||array_diff($resourceScopes,$expected)||!$token->getRefreshToken())throw new DomainException('As permissões/offline não correspondem à integração. Revogue o consentimento deste aplicativo e tente novamente.');
        try{$profile=$this->api->profile($token->getToken());}catch(Throwable $e){$this->revoke((string)$token->getRefreshToken());throw $e;}$email=strtolower((string)($profile['emailAddress']??''));
        if($email!==$this->google['pilot_email']||$email!==strtolower($row['email'])){
            $this->revoke((string)$token->getRefreshToken());
            throw new DomainException('A conta autorizada é diferente da caixa piloto. A conexão não foi salva.');
        }
        $this->store->transaction($account,function()use($account,$pending,$row,$token,$scopes,$email):void{
            $fresh=$this->store->account($account);if((int)$fresh['revision']!==(int)$pending['account_revision'])throw new DomainException('O cadastro mudou. Inicie a conexão novamente.');
            $encrypted=$this->store->cipher->seal(['access_token'=>$token->getToken(),'refresh_token'=>$token->getRefreshToken()],'mail:'.$this->store->owner.':'.$account.':tokens');
            $s=$this->store->pdo->prepare("INSERT INTO core_mail_connections(owner_user_id,account_id,remote_identity,scopes,token_envelope,expires_at,status) VALUES(?,?,?,?,?,?,'connected') ON DUPLICATE KEY UPDATE remote_identity=VALUES(remote_identity),scopes=VALUES(scopes),token_envelope=VALUES(token_envelope),expires_at=VALUES(expires_at),status='connected',generation=generation+1");$s->execute([$this->store->owner,$account,$email,json_encode($scopes),$encrypted,gmdate('Y-m-d H:i:s',$token->getExpires())]);
            $s=$this->store->pdo->prepare('INSERT INTO core_mail_identities(owner_user_id,account_id,email,display_name) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),can_send=1');$s->execute([$this->store->owner,$account,$email,'']);
            $this->store->pdo->prepare("UPDATE core_mail_accounts SET connection_status='connected' WHERE id=? AND owner_user_id=?")->execute([$account,$this->store->owner]);$this->store->audit($account,'oauth.connected');
        });
    }
    public function token(int $account): string
    {
        $this->account($account);
        $connection=$this->store->connection($account);$context='mail:'.$this->store->owner.':'.$account.':tokens';
        $values=$this->store->cipher->open($connection['token_envelope'],$context);if(strtotime($connection['expires_at'].' UTC')>time()+90)return $values['access_token'];
        $lock='mail_refresh:'.$this->store->owner.':'.$account;
        $s=$this->store->pdo->prepare('SELECT GET_LOCK(?,5)');$s->execute([$lock]);if((int)$s->fetchColumn()!==1)throw new DomainException('A renovação está em andamento. Aguarde.');
        try{
            $connection=$this->store->connection($account);$values=$this->store->cipher->open($connection['token_envelope'],$context);if(strtotime($connection['expires_at'].' UTC')>time()+90)return $values['access_token'];
            try{$token=$this->provider()->getAccessToken('refresh_token',['refresh_token'=>$values['refresh_token']]);}
            catch(League\OAuth2\Client\Provider\Exception\IdentityProviderException $e){
                $body=$e->getResponseBody();if(is_array($body)&&($body['error']??'')==='invalid_grant'){
                    $this->store->pdo->prepare("UPDATE core_mail_connections SET status='reauth_required' WHERE id=? AND generation=?")->execute([$connection['id'],$connection['generation']]);
                    $this->store->pdo->prepare("UPDATE core_mail_accounts SET connection_status='reauth_required' WHERE id=? AND owner_user_id=?")->execute([$account,$this->store->owner]);$this->store->audit($account,'oauth.refresh_failed','failure');throw new MailProviderException('auth');
                }throw new MailProviderException('network');
            }catch(Throwable $e){throw new MailProviderException('network');}
            $encrypted=$this->store->cipher->seal(['access_token'=>$token->getToken(),'refresh_token'=>$token->getRefreshToken()?:$values['refresh_token']],$context);
            $s=$this->store->pdo->prepare("UPDATE core_mail_connections SET token_envelope=?,expires_at=? WHERE id=? AND generation=? AND status='connected'");$s->execute([$encrypted,gmdate('Y-m-d H:i:s',$token->getExpires()),$connection['id'],$connection['generation']]);if($s->rowCount()!==1)throw new MailProviderException('auth');return $token->getToken();
        }finally{$s=$this->store->pdo->prepare('SELECT RELEASE_LOCK(?)');$s->execute([$lock]);}
    }
    public function disconnect(int $account): bool
    {
        $this->account($account,false);$s=$this->store->pdo->prepare('SELECT token_envelope FROM core_mail_connections WHERE account_id=? AND owner_user_id=?');$s->execute([$account,$this->store->owner]);$envelope=$s->fetchColumn();$token=null;
        if($envelope){try{$token=$this->store->cipher->open($envelope,'mail:'.$this->store->owner.':'.$account.':tokens')['refresh_token']??null;}catch(Throwable $ignored){}}
        $this->store->transaction($account,function()use($account):void{
            $this->store->pdo->prepare("UPDATE core_mail_connections SET token_envelope=NULL,status='disconnected',generation=generation+1 WHERE account_id=? AND owner_user_id=?")->execute([$account,$this->store->owner]);
            $this->store->pdo->prepare('UPDATE core_mail_identities SET can_send=0 WHERE account_id=? AND owner_user_id=?')->execute([$account,$this->store->owner]);
            $this->store->pdo->prepare("UPDATE core_mail_accounts SET connection_status='not_connected' WHERE id=? AND owner_user_id=?")->execute([$account,$this->store->owner]);$this->store->audit($account,'oauth.disconnected');
        });
        return $token?$this->revoke($token):false;
    }
}
