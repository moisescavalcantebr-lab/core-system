<?php
declare(strict_types=1);

class AuthService
{
    public function __construct(private PDO $pdo) {}

    public function login(string $email, string $password): array|false
    {
        /*
        |--------------------------------------------------------------------------
        | Buscar usuário
        |--------------------------------------------------------------------------
        */

        $stmt = $this->pdo->prepare("
            SELECT * FROM core_users 
            WHERE email = :email 
            LIMIT 1
        ");

        $stmt->execute(['email' => $email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        /*
        |--------------------------------------------------------------------------
        | Credenciais inválidas
        |--------------------------------------------------------------------------
        */

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        if ((int)$user['status'] !== 1) {
            throw new Exception('Conta não ativa.');
        }

        /*
        |--------------------------------------------------------------------------
        | Role (REGRA DO SEU SISTEMA)
        |--------------------------------------------------------------------------
        */

        if (!in_array($user['role'], ['ADMIN','SUPER_ADMIN'])) {
    throw new Exception('Sua conta ainda não foi liberada.');
}

        /*
        |--------------------------------------------------------------------------
        | OK
        |--------------------------------------------------------------------------
        */

        return $user;
    }

    public function createSession(array $user): void
    {
        $_SESSION['core_user'] = [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ];
    }

    public function logout(): void
    {
        unset($_SESSION['core_user']);
        session_destroy();
    }
}