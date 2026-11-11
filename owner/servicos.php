<?php
require_once __DIR__ . '/../includes/auth.php';
exigirOwner();

$pdo = getDB();
$companyId = companyIdDoOwner();
$erro = '';
$sucesso = '';

// Criar serviço (sempre vinculado à empresa da sessão)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar') {
    $nome = trim($_POST['nome'] ?? '');
    $duracao = (int) ($_POST['duracao_min'] ?? 0);
    $preco = (float) ($_POST['preco'] ?? 0);
    $categoria = trim($_POST['categoria'] ?? '');
    $status = $_POST['status'] ?? 'ativo';

    if ($nome === '' || $duracao <= 0) {
        $erro = 'Informe nome e duração válidos.';
    } else {
        $stmt = $pdo->prepare('
            INSERT INTO servicos (company_id, nome, duracao_min, preco, categoria, status)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([$companyId, $nome, $duracao, $preco, $categoria ?: null, $status]);
        $sucesso = 'Serviço cadastrado.';
    }
}

// Atualizar status (sempre restringindo por company_id, mesmo que o id seja manipulado)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'status') {
    $id = (int) ($_POST['id'] ?? 0);
    $novoStatus = $_POST['novo_status'] ?? '';
    if ($id && in_array($novoStatus, ['ativo', 'inativo'], true)) {
        $stmt = $pdo->prepare('UPDATE servicos SET status = ? WHERE id = ? AND company_id = ?');
        $stmt->execute([$novoStatus, $id, $companyId]);
        $sucesso = 'Status atualizado.';
    }
}

// Excluir (restringindo por company_id - isolamento garantido no backend)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'excluir') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('DELETE FROM servicos WHERE id = ? AND company_id = ?');
    $stmt->execute([$id, $companyId]);
    $sucesso = 'Serviço excluído.';
}

// Listagem SEMPRE filtrada pela empresa da sessão
$stmt = $pdo->prepare('SELECT * FROM servicos WHERE company_id = ? ORDER BY nome ASC');
$stmt->execute([$companyId]);
$servicos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Serviços</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<header class="bg-white border-b px-6 py-4 flex items-center justify-between">
  <h1 class="text-lg font-bold text-gray-800">Serviços</h1>
  <a href="/owner/dashboard.php" class="text-sm text-purple-600 hover:underline">← Voltar ao painel</a>
</header>

<main class="max-w-4xl mx-auto p-6 space-y-6">

  <?php if ($erro): ?>
    <div class="bg-red-100 text-red-700 text-sm p-3 rounded"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>
  <?php if ($sucesso): ?>
    <div class="bg-green-100 text-green-700 text-sm p-3 rounded"><?= htmlspecialchars($sucesso) ?></div>
  <?php endif; ?>

  <section class="bg-white rounded-xl shadow p-6">
    <h2 class="text-base font-bold text-gray-800 mb-4">Novo serviço</h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input type="hidden" name="acao" value="criar">
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
        <input type="text" name="nome" required class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Duração (min) *</label>
        <input type="number" name="duracao_min" min="1" required class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Preço (R$)</label>
        <input type="number" step="0.01" name="preco" class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
        <input type="text" name="categoria" class="w-full border rounded px-3 py-2">
      </div>
      <div class="md:col-span-2">
        <button type="submit" class="bg-purple-600 text-white font-medium px-6 py-2 rounded hover:bg-purple-700 transition">
          Cadastrar serviço
        </button>
      </div>
    </form>
  </section>

  <section class="bg-white rounded-xl shadow p-6">
    <h2 class="text-base font-bold text-gray-800 mb-4">Serviços cadastrados (<?= count($servicos) ?>)</h2>
    <?php if (empty($servicos)): ?>
      <p class="text-sm text-gray-500">Nenhum serviço cadastrado ainda.</p>
    <?php else: ?>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-gray-500 border-b">
            <th class="py-2 pr-4">Nome</th>
            <th class="py-2 pr-4">Duração</th>
            <th class="py-2 pr-4">Preço</th>
            <th class="py-2 pr-4">Categoria</th>
            <th class="py-2 pr-4">Status</th>
            <th class="py-2 pr-4">Ações</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($servicos as $s): ?>
            <tr class="border-b last:border-0">
              <td class="py-2 pr-4 font-medium text-gray-800"><?= htmlspecialchars($s['nome']) ?></td>
              <td class="py-2 pr-4 text-gray-600"><?= (int) $s['duracao_min'] ?> min</td>
              <td class="py-2 pr-4 text-gray-600">R$ <?= number_format((float) $s['preco'], 2, ',', '.') ?></td>
              <td class="py-2 pr-4 text-gray-600"><?= htmlspecialchars($s['categoria'] ?? '-') ?></td>
              <td class="py-2 pr-4">
                <form method="POST" class="inline">
                  <input type="hidden" name="acao" value="status">
                  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <select name="novo_status" class="border rounded px-2 py-1 text-xs" onchange="this.form.submit()">
                    <option value="ativo" <?= $s['status'] === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="inativo" <?= $s['status'] === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                  </select>
                </form>
              </td>
              <td class="py-2 pr-4">
                <form method="POST" class="inline" onsubmit="return confirm('Excluir este serviço?')">
                  <input type="hidden" name="acao" value="excluir">
                  <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                  <button type="submit" class="text-red-600 hover:underline text-xs">Excluir</button>
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
