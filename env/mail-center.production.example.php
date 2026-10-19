<?php
// Trecho para integrar ao ENV existente; não substituir a configuração do Core.
// Resolver o ID no banco do servidor por moises.cavalcante@meuprojetoweb.com.
return [
    'enabled'=>false,
    'owner_user_id'=>0,
    'secrets_file'=>'/run/mail-center/secrets.json',
    'oauth_log_redaction_verified'=>false,
    // Preencher somente após conferir a origem HTTPS/proxy e os logs reais.
    'trusted_proxy_ips'=>[],
];
