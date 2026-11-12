<?php
require_once __DIR__ . '/../includes/auth.php';
iniciarSessao();

$pdo = getDB();
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirTokenCsrf();
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM owner_users WHERE email = ? AND ativo = 1 LIMIT 1');
    $stmt->execute([$email]);
    $owner = $stmt->fetch();

    if ($owner && password_verify($senha, $owner['senha_hash'])) {
        renovarSessaoAposLogin();
        $_SESSION['tipo']       = 'owner';
        $_SESSION['owner_id']   = (int) $owner['id'];
        $_SESSION['nome']       = $owner['nome'];
        $_SESSION['company_id'] = $owner['company_id'] ? (int) $owner['company_id'] : null;

        if (empty($_SESSION['company_id'])) {
            header('Location: /owner/empresa_cadastro.php');
        } else {
            header('Location: /owner/dashboard.php');
        }
        exit;
    }
    $erro = 'E-mail ou senha inválidos.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Entrar - Lux ERP</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
<div class="bg-white shadow rounded-xl p-8 w-full max-w-md">
  <h1 class="text-lg font-bold text-gray-800 mb-6">Acesso do proprietário</h1>

  <?php if ($erro): ?>
    <div class="bg-red-100 text-red-700 text-sm p-3 rounded mb-4"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="POST" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
      <input type="email" name="email" required class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
      <input type="password" name="senha" required class="w-full border rounded px-3 py-2">
    </div>
    <button type="submit" class="w-full bg-purple-600 text-white font-medium py-2 rounded hover:bg-purple-700 transition">
      Entrar
    </button>
  </form>

  <p class="text-sm text-gray-500 mt-4">
    Não tem conta? <a href="/owner/cadastro.php" class="text-purple-600 hover:underline">Criar conta</a>
  </p>
</div>
</body>
</html>
