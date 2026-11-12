<?php
require_once __DIR__ . '/../includes/auth.php';
iniciarSessao();

$pdo = getDB();
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirTokenCsrf();
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome === '' || strlen($nome) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($senha) < 8 || strlen($senha) > 72) {
        $erro = 'Informe nome, e-mail válido e senha com pelo menos 8 caracteres.';
    } else {
        $check = $pdo->prepare('SELECT id FROM owner_users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            // Mesmo e-mail já existe: segue para login normal, conforme fluxo atual
            $erro = 'Este e-mail já está cadastrado. Faça login para continuar.';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            // company_id fica NULL até o cadastro da empresa
            $stmt = $pdo->prepare('INSERT INTO owner_users (company_id, nome, email, senha_hash) VALUES (NULL, ?, ?, ?)');
            $stmt->execute([$nome, $email, $hash]);

            renovarSessaoAposLogin();
            $_SESSION['tipo']       = 'owner';
            $_SESSION['owner_id']   = (int) $pdo->lastInsertId();
            $_SESSION['nome']       = $nome;
            $_SESSION['company_id'] = null;

            header('Location: /owner/empresa_cadastro.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Criar conta - Lux ERP</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
<div class="bg-white shadow rounded-xl p-8 w-full max-w-md">
  <h1 class="text-lg font-bold text-gray-800 mb-6">Criar conta de proprietário</h1>

  <?php if ($erro): ?>
    <div class="bg-red-100 text-red-700 text-sm p-3 rounded mb-4"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="POST" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
      <input type="text" name="nome" required class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
      <input type="email" name="email" required class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
      <input type="password" name="senha" required minlength="8" maxlength="72" class="w-full border rounded px-3 py-2">
    </div>
    <button type="submit" class="w-full bg-purple-600 text-white font-medium py-2 rounded hover:bg-purple-700 transition">
      Criar conta
    </button>
  </form>

  <p class="text-sm text-gray-500 mt-4">
    Já tem conta? <a href="/owner/login.php" class="text-purple-600 hover:underline">Entrar</a>
  </p>
</div>
</body>
</html>
