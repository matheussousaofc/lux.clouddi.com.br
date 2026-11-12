<?php
require_once __DIR__ . '/../includes/auth.php';

iniciarSessao();

// Se já logado como profissional, vai direto para a agenda
if (($_SESSION['tipo'] ?? '') === 'profissional') {
    header('Location: /owner/agenda.php');
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!$email || !$senha) {
        $erro = 'Informe e-mail e senha.';
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare('
            SELECT p.*, c.nome_fantasia, c.status AS empresa_status
            FROM profissionais p
            JOIN companies c ON c.id = p.company_id
            WHERE p.email = ?
        ');
        $stmt->execute([$email]);
        $prof = $stmt->fetch();

        if (!$prof || !password_verify($senha, $prof['senha_hash'])) {
            $erro = 'E-mail ou senha inválidos.';
        } elseif ($prof['status'] !== 'ativo') {
            $erro = 'Seu acesso está inativo. Fale com o responsável pelo estabelecimento.';
        } elseif ($prof['empresa_status'] !== 'ativo') {
            $erro = 'O estabelecimento está inativo.';
        } else {
            $_SESSION['tipo']            = 'profissional';
            $_SESSION['profissional_id']  = $prof['id'];
            $_SESSION['company_id']       = $prof['company_id'];
            $_SESSION['nome']             = $prof['nome'];

            header('Location: /owner/agenda.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Login do profissional</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">

<div class="bg-white rounded-xl shadow p-8 w-full max-w-sm">
  <h1 class="text-lg font-bold text-gray-800 mb-1">Acesso do profissional</h1>
  <p class="text-sm text-gray-500 mb-6">Entre com o e-mail e senha cadastrados pelo seu responsável.</p>

  <?php if ($erro): ?>
    <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2 mb-4"><?= htmlspecialchars($erro) ?></p>
  <?php endif; ?>

  <form method="POST" class="space-y-4">
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">E-mail</label>
      <input type="email" name="email" required class="w-full border rounded px-3 py-2 text-sm" autofocus>
    </div>
    <div>
      <label class="block text-xs font-medium text-gray-600 mb-1">Senha</label>
      <input type="password" name="senha" required class="w-full border rounded px-3 py-2 text-sm">
    </div>
    <button type="submit" class="w-full bg-purple-600 text-white rounded py-2 text-sm font-medium hover:bg-purple-700">
      Entrar
    </button>
  </form>
</div>

</body>
</html>
