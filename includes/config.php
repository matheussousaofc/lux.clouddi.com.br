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

function carregarConfiguracaoLocal(): array
{
    $arquivos = [];
    $arquivoAmbiente = obterConfiguracao('LUX_CONFIG_FILE');
    if ($arquivoAmbiente !== null) {
        $arquivos[] = $arquivoAmbiente;
    }

    $arquivos[] = __DIR__ . DIRECTORY_SEPARATOR . 'config.local.php';
    $arquivos[] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'lux-erp-config.php';

    foreach (array_unique($arquivos) as $arquivo) {
        if (!is_file($arquivo) || !is_readable($arquivo)) {
            continue;
        }

        $configuracao = require $arquivo;
        if (is_array($configuracao)) {
            return $configuracao;
        }

        error_log('O arquivo de configuração local do Lux ERP deve retornar um array.');
    }

    return [];
}

$configuracaoLocal = carregarConfiguracaoLocal();

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
