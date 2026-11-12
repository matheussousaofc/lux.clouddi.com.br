<?php
require_once __DIR__ . '/db.php';

function iniciarSessao(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function renovarSessaoAposLogin(): void
{
    iniciarSessao();
    session_regenerate_id(true);
    $_SESSION = [];
}

function tokenCsrf(): string
{
    iniciarSessao();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validarTokenCsrf(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function exigirTokenCsrf(): void
{
    iniciarSessao();
    if (!validarTokenCsrf()) {
        http_response_code(403);
        exit('Requisição inválida. Atualize a página e tente novamente.');
    }
}

function exigirMaster(): void
{
    iniciarSessao();
    if (($_SESSION['tipo'] ?? '') !== 'master') {
        header('Location: login.php');
        exit;
    }
}

function exigirOwner(): void
{
    iniciarSessao();
    if (($_SESSION['tipo'] ?? '') !== 'owner') {
        header('Location: /owner/login.php');
        exit;
    }
    if (empty($_SESSION['company_id'])) {
        header('Location: /owner/empresa_cadastro.php');
        exit;
    }
}

function companyIdDoOwner(): int
{
    iniciarSessao();
    return (int) ($_SESSION['company_id'] ?? 0);
}

function loginMaster(string $email, string $senha): bool
{
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT id, nome, email, senha_hash FROM master_users WHERE email = ? LIMIT 1');
    $stmt->execute([trim($email)]);
    $master = $stmt->fetch();

    if (!$master || !password_verify($senha, $master['senha_hash'])) {
        return false;
    }

    renovarSessaoAposLogin();
    $_SESSION['tipo'] = 'master';
    $_SESSION['master_id'] = (int) $master['id'];
    $_SESSION['nome'] = $master['nome'];

    return true;
}

function logout(): void
{
    iniciarSessao();
    $destino = ($_SESSION['tipo'] ?? '') === 'master'
        ? '/master/login.php'
        : (($_SESSION['tipo'] ?? '') === 'profissional' ? '/profissional/login.php' : '/owner/login.php');

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $parametros = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $parametros['path'],
            $parametros['domain'],
            $parametros['secure'],
            $parametros['httponly']
        );
    }

    session_destroy();
    header('Location: ' . $destino);
    exit;
}
