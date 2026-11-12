<?php
// Conexão e controle de sessão/permissões do sistema

function getDB(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $host = 'localhost';
        $db   = 'u121600320_lux';
        $user = 'u121600320_admin3';     // ajuste conforme seu hPanel
        $pass = '@Entw9gtk953157';         // ajuste conforme seu hPanel
        $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function iniciarSessao(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/** Bloqueia acesso a quem não é master */
function exigirMaster(): void
{
    iniciarSessao();
    if (($_SESSION['tipo'] ?? '') !== 'master') {
        header('Location: /master/login.php');
        exit;
    }
}

/**
 * Bloqueia acesso a quem não é proprietário logado.
 * Se logado mas sem empresa vinculada, redireciona ao cadastro da empresa.
 */
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

/** Retorna o company_id do proprietário logado (isolamento de dados) */
function companyIdDoOwner(): int
{
    iniciarSessao();
    return (int) ($_SESSION['company_id'] ?? 0);
}

function logout(): void
{
    iniciarSessao();
    $_SESSION = [];
    session_destroy();
    header('Location: /index.php');
    exit;
}

/** Autentica um usuário master e preenche a sessão. Retorna true/false. */
function loginMaster(string $email, string $senha): bool
{
    iniciarSessao();
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT * FROM master_users WHERE email = ?');
    $stmt->execute([$email]);
    $master = $stmt->fetch();

    if ($master && password_verify($senha, $master['senha_hash'])) {
        $_SESSION['tipo']      = 'master';
        $_SESSION['master_id'] = (int) $master['id'];
        $_SESSION['nome']      = $master['nome'];
        return true;
    }

    return false;
}
