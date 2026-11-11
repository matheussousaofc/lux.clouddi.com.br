<?php
require_once __DIR__ . '/../includes/auth.php';
exigirMaster();

$pdo = getDB();
$erro = '';
$sucesso = '';

// Cadastro de nova empresa + usuário proprietário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_empresa') {
    $nome_fantasia    = trim($_POST['nome_fantasia'] ?? '');
    $razao_social     = trim($_POST['razao_social'] ?? '');
    $telefone         = trim($_POST['telefone'] ?? '');
    $email_contato    = trim($_POST['email_contato'] ?? '');
    $plano            = trim($_POST['plano'] ?? '');
    $valor_mensalidade = $_POST['valor_mensalidade'] !== '' ? (float) $_POST['valor_mensalidade'] : null;
    $dia_vencimento   = $_POST['dia_vencimento'] !== '' ? (int) $_POST['dia_vencimento'] : null;
    $status_mensalidade = $_POST['status_mensalidade'] ?? 'ativa';

    $owner_nome  = trim($_POST['owner_nome'] ?? '');
    $owner_email = trim($_POST['owner_email'] ?? '');
    $owner_senha = $_POST['owner_senha'] ?? '';

    if ($nome_fantasia === '' || $owner_nome === '' || $owner_email === '' || $owner_senha === '') {
        $erro = 'Preencha nome da empresa e os dados de acesso do proprietário (nome, e-mail, senha).';
    } elseif (strlen($owner_senha) < 6) {
        $erro = 'A senha do proprietário deve ter no mínimo 6 caracteres.';
    } else {
        try {
            // Verifica se e-mail do proprietário já existe
            $check = $pdo->prepare('SELECT id FROM owner_users WHERE email = ?');
            $check->execute([$owner_email]);
            if ($check->fetch()) {
                $erro = 'Já existe um proprietário cadastrado com esse e-mail.';
            } else {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('
                    INSERT INTO companies
                        (nome_fantasia, razao_social, telefone, email_contato, status_mensalidade, plano, valor_mensalidade, dia_vencimento)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([
                    $nome_fantasia,
                    $razao_social ?: null,
                    $telefone ?: null,
                    $email_contato ?: null,
                    $status_mensalidade,
                    $plano ?: null,
                    $valor_mensalidade,
                    $dia_vencimento,
                ]);
                $company_id = (int) $pdo->lastInsertId();

                $hash = password_hash($owner_senha, PASSWORD_DEFAULT);
                $stmtOwner = $pdo->prepare('
                    INSERT INTO owner_users (company_id, nome, email, senha_hash)
                    VALUES (?, ?, ?, ?)
                ');
                $stmtOwner->execute([$company_id, $owner_nome, $owner_email, $hash]);

                // Configuração padrão de agenda (intervalo 30min)
                $stmtSettings = $pdo->prepare('
                    INSERT INTO company_settings (company_id, intervalo_agendamento_min, antecedencia_minima_min)
                    VALUES (?, 30, 0)
                ');
                $stmtSettings->execute([$company_id]);

                $pdo->commit();
                $sucesso = 'Empresa e acesso do proprietário criados com sucesso.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Erro ao criar empresa: ' . $e->getMessage());
            $erro = 'Erro ao salvar. Tente novamente.';
        }
    }
}

// Atualizar status da mensalidade (ação rápida na listagem)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'atualizar_status') {
    $company_id = (int) ($_POST['company_id'] ?? 0);
    $novo_status = $_POST['novo_status'] ?? '';
    $status_validos = ['ativa', 'atrasada', 'suspensa', 'cancelada'];

    if ($company_id && in_array($novo_status, $status_validos, true)) {
        $stmt = $pdo->prepare('UPDATE companies SET status_mensalidade = ? WHERE id = ?');
        $stmt->execute([$novo_status, $company_id]);
        $sucesso = 'Status atualizado.';
    }
}

// Listagem de empresas
$empresas = $pdo->query('
    SELECT c.*, 
           (SELECT COUNT(*) FROM owner_users o WHERE o.company_id = c.id) AS total_owners
    FROM companies c
    ORDER BY c.criado_em DESC
')->fetchAll();

function badgeStatus(string $status): string
{
    $cores = [
        'ativa'     => 'bg-green-100 text-green-700',
        'atrasada'  => 'bg-yellow-100 text-yellow-700',
        'suspensa'  => 'bg-orange-100 text-orange-700',
        'cancelada' => 'bg-red-100 text-red-700',
    ];
    $classe = $cores[$status] ?? 'bg-gray-100 text-gray-700';
    return '<span class="px-2 py-1 rounded-full text-xs font-medium ' . $classe . '">' . htmlspecialchars($status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Empresas - Painel Master</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<header class="bg-white border-b px-6 py-4 flex items-center justify-between">
  <h1 class="text-lg font-bold text-gray-800">Painel Master — Empresas Clientes</h1>
  <div class="flex items-center gap-4">
    <span class="text-sm text-gray-600">Olá, <?= htmlspecialchars($_SESSION['nome'] ?? '') ?></span>
    <a href="/lux_erp/includes/logout.php" class="text-sm text-red-600 hover:underline">Sair</a>
  </div>
</header>

<main class="max-w-6xl mx-auto p-6 space-y-8">

  <?php if ($erro): ?>
    <div class="bg-red-100 text-red-700 text-sm p-3 rounded"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>
  <?php if ($sucesso): ?>
    <div class="bg-green-100 text-green-700 text-sm p-3 rounded"><?= htmlspecialchars($sucesso) ?></div>
  <?php endif; ?>

  <!-- Formulário de cadastro -->
  <section class="bg-white rounded-xl shadow p-6">
    <h2 class="text-base font-bold text-gray-800 mb-4">Cadastrar nova empresa</h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input type="hidden" name="acao" value="criar_empresa">

      <div class="md:col-span-2 border-b pb-2 mb-2">
        <span class="text-sm font-semibold text-gray-600">Dados da empresa</span>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nome fantasia *</label>
        <input type="text" name="nome_fantasia" required class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Razão social</label>
        <input type="text" name="razao_social" class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
        <input type="text" name="telefone" class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">E-mail de contato</label>
        <input type="email" name="email_contato" class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Plano</label>
        <input type="text" name="plano" placeholder="Ex: Mensal, Anual" class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Valor mensalidade (R$)</label>
        <input type="number" step="0.01" name="valor_mensalidade" class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Dia de vencimento</label>
        <input type="number" min="1" max="31" name="dia_vencimento" class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Status da mensalidade</label>
        <select name="status_mensalidade" class="w-full border rounded px-3 py-2">
          <option value="ativa">Ativa</option>
          <option value="atrasada">Atrasada</option>
          <option value="suspensa">Suspensa</option>
          <option value="cancelada">Cancelada</option>
        </select>
      </div>

      <div class="md:col-span-2 border-b pb-2 mb-2 mt-4">
        <span class="text-sm font-semibold text-gray-600">Acesso do proprietário</span>
      </div>

      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Nome do proprietário *</label>
        <input type="text" name="owner_nome" required class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">E-mail de acesso *</label>
        <input type="email" name="owner_email" required class="w-full border rounded px-3 py-2">
      </div>
      <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Senha de acesso *</label>
        <input type="password" name="owner_senha" required minlength="6" class="w-full border rounded px-3 py-2">
      </div>

      <div class="md:col-span-2">
        <button type="submit"
          class="bg-purple-600 text-white font-medium px-6 py-2 rounded hover:bg-purple-700 transition">
          Cadastrar empresa
        </button>
      </div>
    </form>
  </section>

  <!-- Listagem de empresas -->
  <section class="bg-white rounded-xl shadow p-6">
    <h2 class="text-base font-bold text-gray-800 mb-4">Empresas cadastradas (<?= count($empresas) ?>)</h2>

    <?php if (empty($empresas)): ?>
      <p class="text-sm text-gray-500">Nenhuma empresa cadastrada ainda.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 border-b">
              <th class="py-2 pr-4">Empresa</th>
              <th class="py-2 pr-4">Plano</th>
              <th class="py-2 pr-4">Valor</th>
              <th class="py-2 pr-4">Vencimento</th>
              <th class="py-2 pr-4">Status</th>
              <th class="py-2 pr-4">Proprietários</th>
              <th class="py-2 pr-4">Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($empresas as $emp): ?>
              <tr class="border-b last:border-0">
                <td class="py-2 pr-4 font-medium text-gray-800"><?= htmlspecialchars($emp['nome_fantasia']) ?></td>
                <td class="py-2 pr-4 text-gray-600"><?= htmlspecialchars($emp['plano'] ?? '-') ?></td>
                <td class="py-2 pr-4 text-gray-600">
                  <?= $emp['valor_mensalidade'] !== null ? 'R$ ' . number_format((float)$emp['valor_mensalidade'], 2, ',', '.') : '-' ?>
                </td>
                <td class="py-2 pr-4 text-gray-600"><?= $emp['dia_vencimento'] ? 'Dia ' . $emp['dia_vencimento'] : '-' ?></td>
                <td class="py-2 pr-4"><?= badgeStatus($emp['status_mensalidade']) ?></td>
                <td class="py-2 pr-4 text-gray-600"><?= (int) $emp['total_owners'] ?></td>
                <td class="py-2 pr-4">
                  <form method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="acao" value="atualizar_status">
                    <input type="hidden" name="company_id" value="<?= (int) $emp['id'] ?>">
                    <select name="novo_status" class="border rounded px-2 py-1 text-xs"
                      onchange="this.form.submit()">
                      <?php foreach (['ativa','atrasada','suspensa','cancelada'] as $st): ?>
                        <option value="<?= $st ?>" <?= $emp['status_mensalidade'] === $st ? 'selected' : '' ?>>
                          <?= ucfirst($st) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

</main>
</body>
</html>
