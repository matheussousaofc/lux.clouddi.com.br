<?php
require_once __DIR__ . '/../includes/auth.php';
exigirMaster();

$pdo = getDB();

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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Serviços · Painel Master</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
</style>
</head>
<body class="bg-slate-50 min-h-screen flex text-slate-800">

<!-- Sidebar -->
<aside class="w-60 bg-white border-r border-slate-200 flex flex-col shrink-0 min-h-screen">
  <div class="px-6 py-5 border-b border-slate-200">
    <span class="text-base font-bold text-slate-900">Lux ERP</span>
    <p class="text-xs text-slate-400 mt-0.5">Painel Master</p>
  </div>
  <nav class="flex-1 px-3 py-4 space-y-1">
    <a href="/master/dashboard.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>
      Visão geral
    </a>
    <a href="/master/empresas.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2M5 21H3m4-4h2m4 0h2m-6-4h2m4 0h2m-6-4h2m4 0h2" /></svg>
      Empresas
    </a>
    <a href="/master/servicos.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm bg-purple-50 text-purple-700 font-medium">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2" /></svg>
      Serviços
    </a>
  </nav>
  <div class="px-3 py-4 border-t border-slate-200">
    <a href="/includes/logout.php" class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm text-red-600 hover:bg-red-50 transition">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" /></svg>
      Sair
    </a>
  </div>
</aside>

<!-- Main -->
<div class="flex-1 min-h-screen">
  <header class="bg-white border-b border-slate-200 px-8 py-4 flex items-center justify-between">
    <div>
      <h1 class="text-lg font-semibold text-slate-900">Serviços</h1>
      <p class="text-sm text-slate-400">Catálogo de serviços de todas as empresas</p>
    </div>
    <span class="text-sm text-slate-500">Olá, <?= htmlspecialchars($_SESSION['nome'] ?? '') ?></span>
  </header>

  <main class="p-8 space-y-6 max-w-6xl">

    <!-- Filtro -->
    <form method="GET" class="bg-white rounded-xl border border-slate-200 p-4 flex items-center gap-3">
      <label class="text-sm text-slate-600 font-medium">Empresa</label>
      <select name="company_id" onchange="this.form.submit()"
        class="border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
        <option value="0">Todas as empresas</option>
        <?php foreach ($empresas as $e): ?>
          <option value="<?= (int) $e['id'] ?>" <?= $filtroCompany === (int) $e['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($e['nome_fantasia']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span class="text-xs text-slate-400 ml-auto"><?= count($servicos) ?> resultado(s)</span>
    </form>

    <!-- Tabela -->
    <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <?php if (empty($servicos)): ?>
        <div class="p-10 text-center">
          <p class="text-sm text-slate-400">Nenhum serviço encontrado.</p>
        </div>
      <?php else: ?>
        <table class="w-full text-sm">
          <thead class="bg-slate-50 border-b border-slate-200">
            <tr class="text-left text-slate-500">
              <th class="py-3 px-5 font-medium">Empresa</th>
              <th class="py-3 px-5 font-medium">Serviço</th>
              <th class="py-3 px-5 font-medium">Duração</th>
              <th class="py-3 px-5 font-medium">Preço</th>
              <th class="py-3 px-5 font-medium">Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($servicos as $s): ?>
              <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50 transition">
                <td class="py-3 px-5 font-medium text-slate-800"><?= htmlspecialchars($s['nome_fantasia']) ?></td>
                <td class="py-3 px-5 text-slate-600"><?= htmlspecialchars($s['nome']) ?></td>
                <td class="py-3 px-5 text-slate-500"><?= (int) $s['duracao_min'] ?> min</td>
                <td class="py-3 px-5 text-slate-600">R$ <?= number_format((float) $s['preco'], 2, ',', '.') ?></td>
                <td class="py-3 px-5">
                  <?php if ($s['status'] === 'ativo'): ?>
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-green-50 text-green-700">Ativo</span>
                  <?php else: ?>
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-500"><?= htmlspecialchars($s['status']) ?></span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </section>

  </main>
</div>

</body>
</html>
