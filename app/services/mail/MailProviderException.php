<?php
declare(strict_types=1);

final class MailProviderException extends RuntimeException
{
    public function __construct(public readonly string $kind, public readonly int $retryAfter = 0)
    {
        parent::__construct(match ($kind) {
            'auth' => 'A autorização expirou. Reconecte a conta.',
            'quota' => 'O provedor pediu uma pausa. Aguarde antes de atualizar.',
            'missing' => 'A mensagem não está mais disponível no provedor.',
            'cursor' => 'O histórico expirou. Uma atualização limitada será necessária.',
            'unknown' => 'O resultado do envio é incerto. Confira antes de tentar outro envio.',
            default => 'Não foi possível concluir a operação com o provedor.',
        });
    }
}
