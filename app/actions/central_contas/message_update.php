<?php
declare(strict_types=1);
require __DIR__.'/../../bootstrap/bootstrap.php';
mailCenterRequireOwner($pdo,$config);mailCenterRequirePost();http_response_code(403);exit('Este piloto tem leitura e envio. Alterações remotas exigem uma próxima aprovação de permissões.');
