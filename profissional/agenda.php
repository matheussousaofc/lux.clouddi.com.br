<?php
require_once __DIR__ . '/../includes/auth.php';

iniciarSessao();
if (($_SESSION['tipo'] ?? '') !== 'profissional') {
    header('Location: /profissional/login.php');
    exit;
}

header('Location: /owner/agenda.php');
exit;
