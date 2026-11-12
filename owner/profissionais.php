<?php
require_once __DIR__ . '/../includes/auth.php';
exigirOwner();

$pdo = getDB();
$companyId = companyIdDoOwner();

$erro = '';
$sucesso = '';

// Cadastro de novo profissional
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_profissional') {
    $nome  = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($nome === '' || $email === '' || $senha === '') {
        $erro = 'Preencha nome, e-mail e senha de acesso do profissional.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter no mínimo 6 caracteres.';
    } else {
        $check = $pdo->prepare('SELECT id FROM profissionais WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $erro = 'Já existe um profissional cadastrado com esse e-mail.';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('
                INSERT INTO profissionais (company_id, nome, email, senha_hash, status)
                VALUES (?, ?, ?, ?, "ativo")
            ');
            $stmt->execute([$companyId, $nome, $email, $hash]);
            $sucesso = 'Profissional cadastrado com sucesso. O acesso já pode ser utilizado.';
        }
    }
}

// Ativar/inativar profissional
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'alterar_status') {
    $profId = (int) ($_POST['profissional_id'] ?? 0);
    $novoStatus = $_POST['novo_status'] ?? '';

    if ($profId && in_array($novoStatus, ['ativo', 'inativo'], true)) {
        // Garante isolamento: só altera profissional da própria empresa
        $stmt = $pdo->prepare('UPDATE profissionais SET status = ? WHERE id = ? AND company_id = ?');
        $stmt->execute([$novoStatus, $profId, $companyId]);
        $sucesso = 'Status atualizado.';
    }
}

$profissionais = $pdo->prepare('SELECT * FROM profissionais WHERE company_id = ? ORDER BY nome');
$profissionais->execute([$companyId]);
$profissionais = $profissionais->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Profissionais</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<header class="bg-white border-b px-6 py-4 flex items-center justify-between">
  <h1 class="text-lg font-bold text-gray-800">Profissionais</h1>
  <div class="flex items-center gap-4">
    <a href="/owner/dashboard.php" class="text-sm text-purple-600 hover:underline">← Painel</a>
    <a href="/includes/logout.php" class="text-sm text-red-600 hover:underline">Sair</a>
  </div>
</header>

<main class="max-w-4xl mx-auto p-6 space-y-8">

  <?php if ($erro): ?>
    <div class="bg-red-100 text-red-700 text-sm p-3 rounded"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>
  <?php if ($sucesso): ?>
    <div class="bg-green-100 text-green-700 text-sm p-3 rounded"><?= htmlspecialchars($sucesso) ?></div>
  <?php endif; ?>

  <section class="bg-white rounded-xl shadow p-6">
    <h2 class="text-base font-bold text-gray-800 mb-4">Cadastrar novo profissional</h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <input type="hidden" name="acao" value="criar_profissional">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nome completo *</label>
        <input type="text" name="nome" required class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">E-mail de acesso *</label>
        <input type="email" name="email" required class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Senha de acesso *</label>
        <input type="password" name="senha" required minlength="6" class="w-full border rounded px-3 py-2">
      </div>
      <div class="md:col-span-3">
        <button type="submit" class="bg-purple-600 text-white font-medium px-6 py-2 rounded hover:bg-purple-700 transition">
          Cadastrar profissional
        </button>
      </div>
    </form>
    <p class="text-xs text-gray-400 mt-3">
      Os dados complementares (CPF, e-mail alternativo, endereço, foto e observações) são opcionais e
      podem ser preenchidos pelo próprio profissional após o primeiro acesso.
    </p>
  </section>

  <section class="bg-white rounded-xl shadow p-6">
    <h2 class="text-base font-bold text-gray-800 mb-4">Profissionais cadastrados (<?= count($profissionais) ?>)</h2>

    <?php if (empty($profissionais)): ?>
      <p class="text-sm text-gray-500">Nenhum profissional cadastrado ainda.</p>
    <?php else: ?>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-gray-500 border-b">
            <th class="py-2 pr-4">Nome</th>
            <th class="py-2 pr-4">E-mail</th>
            <th class="py-2 pr-4">Status</th>
            <th class="py-2 pr-4">Ação</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($profissionais as $p): ?>
            <tr class="border-b last:border-0">
              <td class="py-2 pr-4 font-medium text-gray-800"><?= htmlspecialchars($p['nome']) ?></td>
              <td class="py-2 pr-4 text-gray-600"><?= htmlspecialchars($p['email']) ?></td>
              <td class="py-2 pr-4">
                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $p['status'] === 'ativo' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' ?>">
                  <?= ucfirst($p['status']) ?>
                </span>
              </td>
              <td class="py-2 pr-4">
                <form method="POST" class="inline">
                  <input type="hidden" name="acao" value="alterar_status">
                  <input type="hidden" name="profissional_id" value="<?= (int) $p['id'] ?>">
                  <input type="hidden" name="novo_status" value="<?= $p['status'] === 'ativo' ? 'inativo' : 'ativo' ?>">
                  <button type="submit" class="text-xs text-purple-600 hover:underline">
                    <?= $p['status'] === 'ativo' ? 'Inativar' : 'Ativar' ?>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>

</main>
</body>
</html>
