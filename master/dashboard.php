<?php
require_once __DIR__ . '/../includes/auth.php';
exigirMaster();

$pdo = getDB();

$totalEmpresas = (int) $pdo->query('SELECT COUNT(*) FROM companies')->fetchColumn();
$ativas    = (int) $pdo->query("SELECT COUNT(*) FROM companies WHERE status_mensalidade = 'ativa'")->fetchColumn();
$atrasadas = (int) $pdo->query("SELECT COUNT(*) FROM companies WHERE status_mensalidade = 'atrasada'")->fetchColumn();
$suspensas = (int) $pdo->query("SELECT COUNT(*) FROM companies WHERE status_mensalidade = 'suspensa'")->fetchColumn();
$canceladas = (int) $pdo->query("SELECT COUNT(*) FROM companies WHERE status_mensalidade = 'cancelada'")->fetchColumn();

$receitaPrevista = (float) $pdo->query("SELECT COALESCE(SUM(valor_mensalidade),0) FROM companies WHERE status_mensalidade = 'ativa'")->fetchColumn();
$receitaEmRisco  = (float) $pdo->query("SELECT COALESCE(SUM(valor_mensalidade),0) FROM companies WHERE status_mensalidade IN ('atrasada','suspensa')")->fetchColumn();

$totalServicos = (int) $pdo->query('SELECT COUNT(*) FROM servicos')->fetchColumn();

$ultimasEmpresas = $pdo->query('
    SELECT id, nome_fantasia, plano, valor_mensalidade, status_mensalidade, criado_em
    FROM companies
    ORDER BY criado_em DESC
    LIMIT 6
')->fetchAll();

function badge(string $status): string
{
    $map = [
        'ativa'     => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'atrasada'  => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'suspensa'  => 'bg-orange-50 text-orange-700 ring-orange-600/20',
        'cancelada' => 'bg-red-50 text-red-700 ring-red-600/20',
    ];
    $cls = $map[$status] ?? 'bg-gray-50 text-gray-700 ring-gray-600/20';
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ring-1 ring-inset ' . $cls . '">' . ucfirst(htmlspecialchars($status)) . '</span>';
}

function moeda(?float $v): string
{
    return $v !== null ? 'R$ ' . number_format($v, 2, ',', '.') : '—';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — Painel Master</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
  body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; }
  ::-webkit-scrollbar { width: 6px; height: 6px; }
  ::-webkit-scrollbar-thumb { background: #e5e7eb; border-radius: 3px; }
</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

<div class="flex min-h-screen">

  <!-- Sidebar -->
  <aside class="w-64 bg-white border-r border-slate-200 flex flex-col shrink-0">
    <div class="h-16 flex items-center gap-2 px-6 border-b border-slate-200">
      <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">L</div>
      <span class="font-semibold text-slate-900 text-sm">Lux ERP · Master</span>
    </div>

    <nav class="flex-1 px-3 py-4 space-y-1">
      <a href="/master/dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium bg-indigo-50 text-indigo-700">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1h3a1 1 0 001-1V10m-9 11h.01"/></svg>
        Dashboard
      </a>
      <a href="/master/empresas.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2M5 21H3m16 0h-5m-6 0h6m-6 0v-5a1 1 0 011-1h2a1 1 0 011 1v5M9 7h1m-1 4h1m4-4h1m-1 4h1"/></svg>
        Empresas
      </a>
      <a href="/master/servicos.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"/></svg>
        Serviços
      </a>
    </nav>

    <div class="p-3 border-t border-slate-200">
      <div class="flex items-center gap-3 px-3 py-2">
        <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-xs font-semibold text-slate-600">
          <?= htmlspecialchars(function_exists('mb_substr') ? mb_substr($_SESSION['nome'] ?? 'M', 0, 1) : substr($_SESSION['nome'] ?? 'M', 0, 1), ENT_QUOTES, 'UTF-8') ?>
        </div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($_SESSION['nome'] ?? '') ?></p>
          <p class="text-xs text-slate-400">Master</p>
        </div>
      </div>
      <a href="/includes/logout.php" class="mt-1 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Sair
      </a>
    </div>
  </aside>

  <!-- Main -->
  <div class="flex-1 min-w-0">
    <header class="h-16 bg-white border-b border-slate-200 flex items-center justify-between px-8">
      <div>
        <h1 class="text-base font-semibold text-slate-900">Dashboard</h1>
        <p class="text-xs text-slate-400">Visão geral de todas as empresas clientes</p>
      </div>
      <a href="/master/empresas.php" class="inline-flex items-center gap-2 bg-indigo-600 text-white text-sm font-medium px-4 py-2 rounded-lg hover:bg-indigo-700 transition">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        Nova empresa
      </a>
    </header>

    <main class="p-8 space-y-8 max-w-6xl">

      <!-- KPIs -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
          <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Empresas ativas</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900"><?= $ativas ?></p>
          <p class="mt-1 text-xs text-slate-400">de <?= $totalEmpresas ?> cadastradas</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
          <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Receita mensal prevista</p>
          <p class="mt-2 text-2xl font-semibold text-emerald-600"><?= moeda($receitaPrevista) ?></p>
          <p class="mt-1 text-xs text-slate-400">mensalidades ativas</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
          <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Receita em risco</p>
          <p class="mt-2 text-2xl font-semibold text-amber-600"><?= moeda($receitaEmRisco) ?></p>
          <p class="mt-1 text-xs text-slate-400"><?= $atrasadas ?> atrasada(s) · <?= $suspensas ?> suspensa(s)</p>
        </div>
        <div class="bg-white rounded-xl border border-slate-200 p-5">
          <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Serviços cadastrados</p>
          <p class="mt-2 text-2xl font-semibold text-slate-900"><?= $totalServicos ?></p>
          <p class="mt-1 text-xs text-slate-400">em todas as empresas</p>
        </div>
      </section>

      <!-- Status breakdown -->
      <section class="bg-white rounded-xl border border-slate-200 p-6">
        <h2 class="text-sm font-semibold text-slate-900 mb-4">Distribuição por status</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <div class="flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
            <div>
              <p class="text-sm font-medium text-slate-800"><?= $ativas ?></p>
              <p class="text-xs text-slate-400">Ativas</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-500"></span>
            <div>
              <p class="text-sm font-medium text-slate-800"><?= $atrasadas ?></p>
              <p class="text-xs text-slate-400">Atrasadas</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full bg-orange-500"></span>
            <div>
              <p class="text-sm font-medium text-slate-800"><?= $suspensas ?></p>
              <p class="text-xs text-slate-400">Suspensas</p>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
            <div>
              <p class="text-sm font-medium text-slate-800"><?= $canceladas ?></p>
              <p class="text-xs text-slate-400">Canceladas</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Últimas empresas -->
      <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
          <h2 class="text-sm font-semibold text-slate-900">Empresas recentes</h2>
          <a href="/master/empresas.php" class="text-xs font-medium text-indigo-600 hover:text-indigo-700">Ver todas →</a>
        </div>

        <?php if (empty($ultimasEmpresas)): ?>
          <p class="text-sm text-slate-400 px-6 py-8 text-center">Nenhuma empresa cadastrada ainda.</p>
        <?php else: ?>
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-slate-400 bg-slate-50 border-b border-slate-200">
                <th class="py-3 px-6 font-medium">Empresa</th>
                <th class="py-3 px-6 font-medium">Plano</th>
                <th class="py-3 px-6 font-medium">Mensalidade</th>
                <th class="py-3 px-6 font-medium">Status</th>
                <th class="py-3 px-6 font-medium">Cadastrada em</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ultimasEmpresas as $e): ?>
                <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60 transition">
                  <td class="py-3 px-6 font-medium text-slate-800"><?= htmlspecialchars($e['nome_fantasia']) ?></td>
                  <td class="py-3 px-6 text-slate-500"><?= htmlspecialchars($e['plano'] ?? '—') ?></td>
                  <td class="py-3 px-6 text-slate-500"><?= moeda($e['valor_mensalidade'] !== null ? (float) $e['valor_mensalidade'] : null) ?></td>
                  <td class="py-3 px-6"><?= badge($e['status_mensalidade']) ?></td>
                  <td class="py-3 px-6 text-slate-400"><?= date('d/m/Y', strtotime($e['criado_em'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>

    </main>
  </div>
</div>

</body>
</html>
