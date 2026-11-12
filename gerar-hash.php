<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$senha = rtrim((string) fgets(STDIN), "\r\n");
if ($senha === '') {
    fwrite(STDERR, "Informe a senha em uma linha da entrada padrão.\n");
    exit(1);
}

echo password_hash($senha, PASSWORD_DEFAULT) . PHP_EOL;
