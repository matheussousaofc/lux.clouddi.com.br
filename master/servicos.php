<?php
require_once __DIR__ . '/../includes/auth.php';
exigirMaster();

$pdo = getDB();

// Master vê todos os serviços de todas as empresas (acesso global, sem filtro de company_id)
$filtroCompany = isset($_GET['company_id']) ? (int) $_GET['company_id'] : 0;

$sql = '
    SELECT s.*, c.nome_fantasia
    FROM servicos s
    JOIN companies c ON c.id = s.company_id
';
$params = [];
if ($filtroCompany > 0) {
    $sql .= ' WHERE s.company_id = ?';
    $params[] = $filtroCompany;
}
$sql .= ' ORDER BY c.nome_fantasia, s.nome';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$servicos = $stmt->fetchAll();

$empresas = $pdo->query('SELECT id, nome_fantasia FROM companies ORDER BY nome_fantasia')->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Serviços - Todas as empresas</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<header class="bg-white border-b px-6 py-4 flex items-center justify-between">
  <h1 class="text-lg font-bold text-gray-800">Serviços — Visão Master</h1>
  <a href="/master/empresas.php" class="text-sm text-purple-600 hover:underline">← Empresas</a>
</header>

<main class="max-w-5xl mx-auto p-6 space-y-6">
  <form method="GET" class="bg-white rounded-xl shadow p-4 flex items-center gap-3">
    <label class="text-sm text-gray-600">Filtrar por empresa:</label>
    <select name="company_id" onchange="this.form.submit()" class="border rounded px-3 py-2 text-sm">
      <option value="0">Todas as empresas</option>
      <?php foreach ($empresas as $e): ?>
        <option value="<?= (int) $e['id'] ?>" <?= $filtroCompany === (int) $e['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($e['nome_fantasia']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </form>

  <section class="bg-white rounded-xl shadow p-6">
    <h2 class="text-base font-bold text-gray-800 mb-4">Resultados (<?= count($servicos) ?>)</h2>
    <?php if (empty($servicos)): ?>
      <p class="text-sm text-gray-500">Nenhum serviço encontrado.</p>
    <?php else: ?>
      <table class="w-full text-sm">
        <thead>
          <tr class="text-left text-gray-500 border-b">
            <th class="py-2 pr-4">Empresa</th>
            <th class="py-2 pr-4">Serviço</th>
            <th class="py-2 pr-4">Duração</th>
            <th class="py-2 pr-4">Preço</th>
            <th class="py-2 pr-4">Status</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($servicos as $s): ?>
            <tr class="border-b last:border-0">
              <td class="py-2 pr-4 font-medium text-gray-800"><?= htmlspecialchars($s['nome_fantasia']) ?></td>
              <td class="py-2 pr-4 text-gray-600"><?= htmlspecialchars($s['nome']) ?></td>
              <td class="py-2 pr-4 text-gray-600"><?= (int) $s['duracao_min'] ?> min</td>
              <td class="py-2 pr-4 text-gray-600">R$ <?= number_format((float) $s['preco'], 2, ',', '.') ?></td>
              <td class="py-2 pr-4 text-gray-600"><?= htmlspecialchars($s['status']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>
</body>
</html>
