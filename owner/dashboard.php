<?php
require_once __DIR__ . '/../includes/auth.php';
exigirOwner();

$pdo = getDB();
$companyId = companyIdDoOwner();

$empresa = $pdo->prepare('SELECT * FROM companies WHERE id = ?');
$empresa->execute([$companyId]);
$empresa = $empresa->fetch();

$totalServicos = $pdo->prepare('SELECT COUNT(*) AS total FROM servicos WHERE company_id = ?');
$totalServicos->execute([$companyId]);
$totalServicos = (int) $totalServicos->fetch()['total'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Painel - <?= htmlspecialchars($empresa['nome_fantasia'] ?? '') ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">
<header class="bg-white border-b px-6 py-4 flex items-center justify-between">
  <h1 class="text-lg font-bold text-gray-800"><?= htmlspecialchars($empresa['nome_fantasia'] ?? 'Minha Empresa') ?></h1>
  <div class="flex items-center gap-4">
    <a href="/owner/servicos.php" class="text-sm text-purple-600 hover:underline">Serviços</a>
    <span class="text-sm text-gray-600">Olá, <?= htmlspecialchars($_SESSION['nome'] ?? '') ?></span>
    <a href="/includes/logout.php" class="text-sm text-red-600 hover:underline">Sair</a>
  </div>
</header>

<main class="max-w-4xl mx-auto p-6">
  <div class="bg-white rounded-xl shadow p-6">
    <p class="text-sm text-gray-500 mb-1">Serviços cadastrados</p>
    <p class="text-2xl font-bold text-gray-800"><?= $totalServicos ?></p>
    <a href="/owner/servicos.php" class="inline-block mt-4 text-sm text-purple-600 hover:underline">Gerenciar serviços →</a>
  </div>
</main>
</body>
</html>
