<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');

function obterConfiguracao(string $nome): ?string
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

$configuracaoLocal = [];
$arquivoConfiguracao = obterConfiguracao('LUX_CONFIG_FILE');
if ($arquivoConfiguracao === null) {
    $arquivoConfiguracao = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lux-erp-config.php';
}

if (is_file($arquivoConfiguracao) && is_readable($arquivoConfiguracao)) {
    $configuracaoCarregada = require $arquivoConfiguracao;
    if (is_array($configuracaoCarregada)) {
        $configuracaoLocal = $configuracaoCarregada;
    } else {
        error_log('O arquivo de configuração local do Lux ERP deve retornar um array.');
    }
}

function obterConfiguracaoBanco(string $nome, array $configuracaoLocal): string
{
    $valor = obterConfiguracao($nome);
    if ($valor === null) {
        $valorLocal = $configuracaoLocal[$nome] ?? null;
        $valor = is_string($valorLocal) && $valorLocal !== '' ? $valorLocal : null;
    }

    return $valor ?? '';
}

define('DB_HOST', obterConfiguracaoBanco('DB_HOST', $configuracaoLocal));
define('DB_NAME', obterConfiguracaoBanco('DB_NAME', $configuracaoLocal));
define('DB_USER', obterConfiguracaoBanco('DB_USER', $configuracaoLocal));
define('DB_PASS', obterConfiguracaoBanco('DB_PASS', $configuracaoLocal));

if (session_status() === PHP_SESSION_NONE) {
    $https = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params(0, '/', '', $https, true);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}
