<?php
// Conexão e controle de sessão/permissões do sistema
require_once __DIR__ . '/db.php';

function iniciarSessao(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function configuracaoAmbiente(string $nome): ?string
{
    $valor = getenv($nome);
    if (is_string($valor) && $valor !== '') {
        return $valor;
    }

    foreach ([$_SERVER, $_ENV] as $origem) {
        $valor = $origem[$nome] ?? null;
        if (is_string($valor) && $valor !== '') {
            return $valor;
        }
    }

    return null;
}

/**
 * Autentica o usuário master com credenciais configuradas no ambiente.
 */
function loginMaster(string $email, string $senha): bool
{
    $emailConfigurado = configuracaoAmbiente('MASTER_EMAIL');
    $hashSenhaConfigurado = configuracaoAmbiente('MASTER_PASSWORD_HASH');

    if (
        $emailConfigurado === null
        || $hashSenhaConfigurado === null
    ) {
        error_log('Autenticação master indisponível: configure MASTER_EMAIL e MASTER_PASSWORD_HASH.');
        return false;
    }

    if (
        !hash_equals(strtolower(trim($emailConfigurado)), strtolower(trim($email)))
        || !password_verify($senha, $hashSenhaConfigurado)
    ) {
        return false;
    }

    iniciarSessao();
    session_regenerate_id(true);
    $_SESSION = [];
    $_SESSION['tipo'] = 'master';
    $_SESSION['nome'] = $emailConfigurado;

    return true;
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

    $destino = ($_SESSION['tipo'] ?? '') === 'master'
        ? '/master/login.php'
        : '/owner/login.php';

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
