<?php
require_once __DIR__ . '/../includes/auth.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Preencha e-mail e senha.';
    } elseif (loginMaster($email, $senha)) {
        header('Location: /master/empresas.php');
        exit;
    } else {
        $erro = 'E-mail ou senha inválidos.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Login Master - Lux ERP</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
  <form method="POST" class="bg-white p-8 rounded-xl shadow-md w-full max-w-sm">
    <h1 class="text-xl font-bold mb-6 text-gray-800">Acesso Master</h1>

    <?php if ($erro): ?>
      <div class="bg-red-100 text-red-700 text-sm p-3 rounded mb-4">
        <?= htmlspecialchars($erro) ?>
      </div>
    <?php endif; ?>

    <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
    <input type="email" name="email" required
      class="w-full border rounded px-3 py-2 mb-4 focus:outline-none focus:ring-2 focus:ring-purple-500">

    <label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
    <input type="password" name="senha" required
      class="w-full border rounded px-3 py-2 mb-6 focus:outline-none focus:ring-2 focus:ring-purple-500">

    <button type="submit"
      class="w-full bg-purple-600 text-white font-medium py-2 rounded hover:bg-purple-700 transition">
      Entrar
    </button>
  </form>
</body>
</html>
