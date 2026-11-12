<?php
require_once __DIR__ . '/../includes/auth.php';
exigirMaster();

$pdo = getDB();
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirTokenCsrf();
}

// Cadastro de nova empresa + usuário proprietário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'criar_empresa') {
    $nome_fantasia    = trim($_POST['nome_fantasia'] ?? '');
    $razao_social     = trim($_POST['razao_social'] ?? '');
    $telefone         = trim($_POST['telefone'] ?? '');
    $email_contato    = trim($_POST['email_contato'] ?? '');
    $plano            = trim($_POST['plano'] ?? '');
    $valorInformado = trim((string) ($_POST['valor_mensalidade'] ?? ''));
    $valor_mensalidade = $valorInformado !== '' ? filter_var($valorInformado, FILTER_VALIDATE_FLOAT) : null;
    $diaInformado = trim((string) ($_POST['dia_vencimento'] ?? ''));
    $dia_vencimento = $diaInformado !== '' ? filter_var($diaInformado, FILTER_VALIDATE_INT) : null;
    $status_mensalidade = $_POST['status_mensalidade'] ?? 'ativa';

    $owner_nome  = trim($_POST['owner_nome'] ?? '');
    $owner_email = trim($_POST['owner_email'] ?? '');
    $owner_senha = $_POST['owner_senha'] ?? '';

    if ($nome_fantasia === '' || $owner_nome === '' || $owner_email === '' || $owner_senha === '') {
        $erro = 'Preencha nome da empresa e os dados de acesso do proprietário (nome, e-mail, senha).';
    } elseif (!filter_var($owner_email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido para o proprietário.';
    } elseif (strlen($owner_senha) < 8 || strlen($owner_senha) > 72) {
        $erro = 'A senha do proprietário deve ter entre 8 e 72 caracteres.';
    } elseif (
        ($valor_mensalidade !== null && ($valor_mensalidade === false || $valor_mensalidade < 0))
        || ($dia_vencimento !== null && ($dia_vencimento === false || $dia_vencimento < 1 || $dia_vencimento > 31))
        || !in_array($status_mensalidade, ['ativa', 'atrasada', 'suspensa', 'cancelada'], true)
    ) {
        $erro = 'Confira o valor, o dia de vencimento e o status informados.';
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
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
        'ativa'     => 'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/20',
        'atrasada'  => 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/20',
        'suspensa'  => 'bg-orange-50 text-orange-700 ring-1 ring-inset ring-orange-600/20',
        'cancelada' => 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/20',
    ];
    $classe = $cores[$status] ?? 'bg-slate-50 text-slate-700 ring-1 ring-inset ring-slate-600/20';
    return '<span class="px-2.5 py-0.5 rounded-full text-xs font-medium ' . $classe . '">' . ucfirst(htmlspecialchars($status)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Empresas - Painel Master</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>body { font-family: 'Segoe UI', Tahoma, Arial, sans-serif; }</style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen">

<div class="flex min-h-screen">
  <aside class="w-64 bg-white border-r border-slate-200 flex flex-col shrink-0">
    <div class="h-16 flex items-center gap-2 px-6 border-b border-slate-200">
      <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-sm">L</div>
      <span class="font-semibold text-slate-900 text-sm">Lux ERP · Master</span>
    </div>
    <nav class="flex-1 px-3 py-4 space-y-1">
      <a href="/master/dashboard.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition">Dashboard</a>
      <a href="/master/empresas.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium bg-indigo-50 text-indigo-700">Empresas</a>
      <a href="/master/servicos.php" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition">Serviços</a>
    </nav>
    <div class="p-3 border-t border-slate-200">
      <div class="flex items-center gap-3 px-3 py-2">
        <div class="w-8 h-8 rounded-full bg-slate-200 flex items-center justify-center text-xs font-semibold text-slate-600"><?= htmlspecialchars(function_exists('mb_substr') ? mb_substr($_SESSION['nome'] ?? 'M', 0, 1) : substr($_SESSION['nome'] ?? 'M', 0, 1), ENT_QUOTES, 'UTF-8') ?></div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-medium text-slate-800 truncate"><?= htmlspecialchars($_SESSION['nome'] ?? '') ?></p>
          <p class="text-xs text-slate-400">Master</p>
        </div>
      </div>
      <a href="/includes/logout.php" class="mt-1 flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-medium text-red-600 hover:bg-red-50 transition">Sair</a>
    </div>
  </aside>

  <div class="flex-1 min-w-0">
    <header class="h-16 bg-white border-b border-slate-200 flex items-center px-8">
      <div>
        <h1 class="text-base font-semibold text-slate-900">Empresas clientes</h1>
        <p class="text-xs text-slate-400">Cadastro e gestão de empresas e mensalidades</p>
      </div>
    </header>

<main class="p-8 space-y-8 max-w-6xl">

  <?php if ($erro): ?>
    <div class="bg-red-100 text-red-700 text-sm p-3 rounded"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>
  <?php if ($sucesso): ?>
    <div class="bg-green-100 text-green-700 text-sm p-3 rounded"><?= htmlspecialchars($sucesso) ?></div>
  <?php endif; ?>

  <!-- Formulário de cadastro -->
  <section class="bg-white rounded-xl border border-slate-200 p-6">
    <h2 class="text-sm font-semibold text-slate-900 mb-4">Cadastrar nova empresa</h2>
    <form method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <input type="hidden" name="acao" value="criar_empresa">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="md:col-span-2 border-b border-slate-200 pb-2 mb-2">
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Dados da empresa</span>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nome fantasia *</label>
        <input type="text" name="nome_fantasia" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Razão social</label>
        <input type="text" name="razao_social" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Telefone</label>
        <input type="text" name="telefone" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">E-mail de contato</label>
        <input type="email" name="email_contato" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Plano</label>
        <input type="text" name="plano" placeholder="Ex: Mensal, Anual" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Valor mensalidade (R$)</label>
        <input type="number" step="0.01" name="valor_mensalidade" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Dia de vencimento</label>
        <input type="number" min="1" max="31" name="dia_vencimento" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Status da mensalidade</label>
        <select name="status_mensalidade" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
          <option value="ativa">Ativa</option>
          <option value="atrasada">Atrasada</option>
          <option value="suspensa">Suspensa</option>
          <option value="cancelada">Cancelada</option>
        </select>
      </div>

      <div class="md:col-span-2 border-b border-slate-200 pb-2 mb-2 mt-4">
        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide">Acesso do proprietário</span>
      </div>

      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Nome do proprietário *</label>
        <input type="text" name="owner_nome" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">E-mail de acesso *</label>
        <input type="email" name="owner_email" required class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>
      <div>
        <label class="block text-sm font-medium text-slate-700 mb-1">Senha de acesso *</label>
        <input type="password" name="owner_senha" required minlength="8" maxlength="72" class="w-full border border-slate-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
      </div>

      <div class="md:col-span-2 pt-2">
        <button type="submit"
          class="bg-indigo-600 text-white text-sm font-medium px-6 py-2.5 rounded-lg hover:bg-indigo-700 transition">
          Cadastrar empresa
        </button>
      </div>
    </form>
  </section>

  <!-- Listagem de empresas -->
  <section class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
      <h2 class="text-sm font-semibold text-slate-900">Empresas cadastradas (<?= count($empresas) ?>)</h2>
    </div>

    <?php if (empty($empresas)): ?>
      <p class="text-sm text-slate-400 px-6 py-8 text-center">Nenhuma empresa cadastrada ainda.</p>
    <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-slate-400 bg-slate-50 border-b border-slate-200">
              <th class="py-3 px-6 font-medium">Empresa</th>
              <th class="py-3 px-6 font-medium">Plano</th>
              <th class="py-3 px-6 font-medium">Valor</th>
              <th class="py-3 px-6 font-medium">Vencimento</th>
              <th class="py-3 px-6 font-medium">Status</th>
              <th class="py-3 px-6 font-medium">Proprietários</th>
              <th class="py-3 px-6 font-medium">Ação</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($empresas as $emp): ?>
              <tr class="border-b border-slate-100 last:border-0 hover:bg-slate-50/60 transition">
                <td class="py-3 px-6 font-medium text-slate-800"><?= htmlspecialchars($emp['nome_fantasia']) ?></td>
                <td class="py-3 px-6 text-slate-500"><?= htmlspecialchars($emp['plano'] ?? '-') ?></td>
                <td class="py-3 px-6 text-slate-500">
                  <?= $emp['valor_mensalidade'] !== null ? 'R$ ' . number_format((float)$emp['valor_mensalidade'], 2, ',', '.') : '-' ?>
                </td>
                <td class="py-3 px-6 text-slate-500"><?= $emp['dia_vencimento'] ? 'Dia ' . $emp['dia_vencimento'] : '-' ?></td>
                <td class="py-3 px-6"><?= badgeStatus($emp['status_mensalidade']) ?></td>
                <td class="py-3 px-6 text-slate-500"><?= (int) $emp['total_owners'] ?></td>
                <td class="py-3 px-6">
                  <form method="POST" class="flex items-center gap-2">
                    <input type="hidden" name="acao" value="atualizar_status">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="company_id" value="<?= (int) $emp['id'] ?>">
                    <select name="novo_status" class="border border-slate-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-indigo-500"
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
  </div>
</div>
</body>
</html>
