<?php
/**
 * Agenda do salão — acessível pelo owner e pelo profissional (via sessão própria).
 * O profissional sempre vê a agenda completa; só pode EDITAR/CRIAR em nome de
 * outro profissional se tiver a permissão pode_gerenciar_agenda_geral = 1.
 */
require_once __DIR__ . '/../includes/auth.php';

iniciarSessao();
$tipoUsuario = $_SESSION['tipo'] ?? '';
if ($tipoUsuario !== 'owner' && $tipoUsuario !== 'profissional') {
    header('Location: /owner/login.php');
    exit;
}

$pdo = getDB();

// Resolve company_id e dados do usuário logado, conforme o tipo
if ($tipoUsuario === 'owner') {
    $companyId        = companyIdDoOwner();
    $usuarioId         = (int) $_SESSION['owner_id'];
    $usuarioNome       = $_SESSION['nome'];
    $podeGerenciarTudo = true; // owner sempre pode tudo
    $meuProfissionalId = null; // owner não é um profissional
} else {
    $companyId = (int) $_SESSION['company_id'];
    $usuarioId = (int) $_SESSION['profissional_id'];
    $usuarioNome = $_SESSION['nome'];

    $stmtPerm = $pdo->prepare('SELECT pode_gerenciar_agenda_geral FROM profissionais WHERE id = ?');
    $stmtPerm->execute([$usuarioId]);
    $podeGerenciarTudo = (bool) $stmtPerm->fetchColumn();
    $meuProfissionalId = $usuarioId;
}

header('Content-Type: application/json; charset=utf-8', true);
$isAjax = isset($_GET['acao']) || (isset($_POST['acao']));
if (!$isAjax) {
    header('Content-Type: text/html; charset=utf-8');
}

/** Verifica conflito de horário para um profissional, ignorando o próprio agendamento em edição */
function existeConflito(PDO $pdo, int $companyId, int $profissionalId, string $data, string $inicio, string $fim, int $ignorarId = 0): bool
{
    $sql = '
        SELECT COUNT(*) FROM agendamentos
        WHERE company_id = ?
          AND profissional_id = ?
          AND data_atendimento = ?
          AND status != "cancelado"
          AND id != ?
          AND (? < hora_fim AND ? > hora_inicio)
    ';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$companyId, $profissionalId, $data, $ignorarId, $inicio, $fim]);
    return (int) $stmt->fetchColumn() > 0;
}

// ===================== AÇÕES AJAX =====================

if (isset($_GET['acao']) && $_GET['acao'] === 'listar') {
    $data = $_GET['data'] ?? date('Y-m-d');

    $stmt = $pdo->prepare('
        SELECT a.*, s.nome AS servico_nome, p.nome AS profissional_nome
        FROM agendamentos a
        JOIN servicos s ON s.id = a.servico_id
        JOIN profissionais p ON p.id = a.profissional_id
        WHERE a.company_id = ? AND a.data_atendimento = ?
        ORDER BY a.hora_inicio
    ');
    $stmt->execute([$companyId, $data]);
    echo json_encode(['ok' => true, 'agendamentos' => $stmt->fetchAll()]);
    exit;
}

if (isset($_GET['acao']) && $_GET['acao'] === 'opcoes') {
    $servicos = $pdo->prepare('SELECT id, nome, duracao_min, preco FROM servicos WHERE company_id = ? AND status = "ativo" ORDER BY nome');
    $servicos->execute([$companyId]);

    $profissionais = $pdo->prepare('SELECT id, nome FROM profissionais WHERE company_id = ? AND status = "ativo" ORDER BY nome');
    $profissionais->execute([$companyId]);

    echo json_encode([
        'ok' => true,
        'servicos' => $servicos->fetchAll(),
        'profissionais' => $profissionais->fetchAll(),
        'podeGerenciarTudo' => $podeGerenciarTudo,
        'meuProfissionalId' => $meuProfissionalId,
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    $id              = (int) ($_POST['id'] ?? 0);
    $servicoId       = (int) ($_POST['servico_id'] ?? 0);
    $profissionalId  = (int) ($_POST['profissional_id'] ?? 0);
    $data            = trim($_POST['data_atendimento'] ?? '');
    $horaInicio      = trim($_POST['hora_inicio'] ?? '');
    $horaFim         = trim($_POST['hora_fim'] ?? '');
    $valor           = (float) ($_POST['valor'] ?? 0);
    $clienteId       = (int) ($_POST['cliente_id'] ?? 0);

    // Regra de permissão: profissional sem permissão geral só pode criar/editar para si mesmo
    if ($tipoUsuario === 'profissional' && !$podeGerenciarTudo && $profissionalId !== $meuProfissionalId) {
        echo json_encode(['ok' => false, 'erro' => 'Você não tem permissão para gerenciar a agenda de outro profissional.']);
        exit;
    }

    if (!$servicoId || !$profissionalId || !$data || !$horaInicio || !$horaFim) {
        echo json_encode(['ok' => false, 'erro' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }

    if ($horaFim <= $horaInicio) {
        echo json_encode(['ok' => false, 'erro' => 'O horário de término deve ser após o início.']);
        exit;
    }

    if (existeConflito($pdo, $companyId, $profissionalId, $data, $horaInicio, $horaFim, $id)) {
        echo json_encode(['ok' => false, 'erro' => 'Este horário já está ocupado para o profissional selecionado.']);
        exit;
    }

    try {
        if ($id > 0) {
            // Edição — mantém quem criou, registra quem alterou
            $stmt = $pdo->prepare('
                UPDATE agendamentos SET
                    servico_id = ?, profissional_id = ?, data_atendimento = ?,
                    hora_inicio = ?, hora_fim = ?, valor = ?,
                    atualizado_por_tipo = ?, atualizado_por_id = ?, atualizado_por_nome = ?, atualizado_em = NOW()
                WHERE id = ? AND company_id = ?
            ');
            $stmt->execute([
                $servicoId, $profissionalId, $data, $horaInicio, $horaFim, $valor,
                $tipoUsuario, $usuarioId, $usuarioNome,
                $id, $companyId,
            ]);
            echo json_encode(['ok' => true, 'mensagem' => 'Agendamento atualizado com sucesso.']);
        } else {
            // Criação — sempre registra quem realizou, independente do profissional selecionado
            $stmt = $pdo->prepare('
                INSERT INTO agendamentos
                    (company_id, profissional_id, servico_id, cliente_id, data_atendimento,
                     hora_inicio, hora_fim, valor, status, criado_por_tipo, criado_por_id, criado_por_nome)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, "agendado", ?, ?, ?)
            ');
            $stmt->execute([
                $companyId, $profissionalId, $servicoId, $clienteId ?: null, $data,
                $horaInicio, $horaFim, $valor,
                $tipoUsuario, $usuarioId, $usuarioNome,
            ]);
            echo json_encode(['ok' => true, 'mensagem' => 'Agendamento criado com sucesso.']);
        }
    } catch (Exception $e) {
        error_log('Erro ao salvar agendamento: ' . $e->getMessage());
        echo json_encode(['ok' => false, 'erro' => 'Erro ao salvar. Tente novamente.']);
    }
    exit;
}

if (isset($_GET['acao']) && $_GET['acao'] === 'detalhe') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM agendamentos WHERE id = ? AND company_id = ?');
    $stmt->execute([$id, $companyId]);
    $ag = $stmt->fetch();
    echo json_encode(['ok' => (bool) $ag, 'agendamento' => $ag]);
    exit;
}

// ===================== PÁGINA HTML =====================
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Agenda</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<header class="bg-white border-b px-6 py-4 flex items-center justify-between">
  <h1 class="text-lg font-bold text-gray-800">Agenda do salão</h1>
  <span class="text-sm text-gray-600">Logado como <?= htmlspecialchars($usuarioNome) ?> (<?= $tipoUsuario ?>)</span>
</header>

<main class="max-w-5xl mx-auto p-6 space-y-6">

  <div class="bg-white rounded-xl shadow p-4 flex items-center gap-3">
    <label class="text-sm text-gray-600">Data:</label>
    <input type="date" id="filtroData" class="border rounded px-3 py-2 text-sm">
    <span class="text-xs text-gray-400 ml-auto">Clique em um horário livre para agendar. Clique em um agendamento existente para editar.</span>
  </div>

  <div id="gradeHorarios" class="bg-white rounded-xl shadow p-6">
    <p class="text-sm text-gray-400">Carregando agenda...</p>
  </div>

</main>

<!-- Modal de criação/edição -->
<div id="modalAgendamento" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
  <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 space-y-4 max-h-[90vh] overflow-y-auto">
    <h2 id="modalTitulo" class="text-base font-bold text-gray-800">Novo agendamento</h2>

    <div id="modalEtapaForm" class="space-y-3">
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Serviço *</label>
        <select id="campoServico" class="w-full border rounded px-3 py-2 text-sm"></select>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Profissional responsável *</label>
        <select id="campoProfissional" class="w-full border rounded px-3 py-2 text-sm"></select>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Data do atendimento *</label>
          <input type="date" id="campoData" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Duração (min)</label>
          <input type="number" id="campoDuracao" class="w-full border rounded px-3 py-2 text-sm bg-gray-50" readonly>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Horário de início *</label>
          <input type="time" id="campoHoraInicio" class="w-full border rounded px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-gray-600 mb-1">Horário de término</label>
          <input type="time" id="campoHoraFim" class="w-full border rounded px-3 py-2 text-sm bg-gray-50" readonly>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Valor (R$) *</label>
        <input type="number" step="0.01" id="campoValor" class="w-full border rounded px-3 py-2 text-sm">
      </div>

      <p id="modalErro" class="text-sm text-red-600 hidden"></p>

      <div class="flex justify-end gap-2 pt-2">
        <button onclick="fecharModal()" class="px-4 py-2 text-sm text-gray-600 hover:underline">Cancelar</button>
        <button onclick="irParaConfirmacao()" class="px-4 py-2 text-sm bg-purple-600 text-white rounded hover:bg-purple-700">Continuar</button>
      </div>
    </div>

    <!-- Etapa de confirmação -->
    <div id="modalEtapaConfirmacao" class="hidden space-y-3">
      <div class="bg-gray-50 rounded-lg p-4 text-sm text-gray-700 space-y-1">
        <p><strong>Serviço:</strong> <span id="resumoServico"></span></p>
        <p><strong>Profissional:</strong> <span id="resumoProfissional"></span></p>
        <p><strong>Data:</strong> <span id="resumoData"></span></p>
        <p><strong>Horário:</strong> <span id="resumoHorario"></span></p>
        <p><strong>Valor:</strong> <span id="resumoValor"></span></p>
        <p class="text-xs text-gray-400 pt-1">Registrado por: <?= htmlspecialchars($usuarioNome) ?></p>
      </div>
      <p id="confirmacaoErro" class="text-sm text-red-600 hidden"></p>
      <div class="flex justify-end gap-2 pt-2">
        <button onclick="voltarParaForm()" class="px-4 py-2 text-sm text-gray-600 hover:underline">Voltar e editar</button>
        <button onclick="confirmarSalvar()" class="px-4 py-2 text-sm bg-green-600 text-white rounded hover:bg-green-700">Confirmar agendamento</button>
      </div>
    </div>
  </div>
</div>

<script>
const BASE = window.location.pathname;
let servicosDisponiveis = [];
let profissionaisDisponiveis = [];
let podeGerenciarTudo = <?= $podeGerenciarTudo ? 'true' : 'false' ?>;
let meuProfissionalId = <?= $meuProfissionalId !== null ? (int) $meuProfissionalId : 'null' ?>;
let agendamentoEmEdicaoId = 0;

document.getElementById('filtroData').value = new Date().toISOString().slice(0, 10);

async function carregarOpcoes() {
  const res = await fetch(BASE + '?acao=opcoes');
  const json = await res.json();
  servicosDisponiveis = json.servicos;
  profissionaisDisponiveis = json.profissionais;

  const selServico = document.getElementById('campoServico');
  selServico.innerHTML = servicosDisponiveis.map(s =>
    `<option value="${s.id}" data-duracao="${s.duracao_min}" data-preco="${s.preco}">${s.nome}</option>`
  ).join('');

  const selProf = document.getElementById('campoProfissional');
  selProf.innerHTML = profissionaisDisponiveis.map(p =>
    `<option value="${p.id}" ${(!podeGerenciarTudo && p.id !== meuProfissionalId) ? 'disabled' : ''}>${p.nome}${(!podeGerenciarTudo && p.id !== meuProfissionalId) ? ' (sem permissão)' : ''}</option>`
  ).join('');
}

function recalcularDuracaoEValor() {
  const opt = document.getElementById('campoServico').selectedOptions[0];
  if (!opt) return;
  document.getElementById('campoDuracao').value = opt.dataset.duracao;
  document.getElementById('campoValor').value = parseFloat(opt.dataset.preco).toFixed(2);
  recalcularHoraFim();
}

function recalcularHoraFim() {
  const inicio = document.getElementById('campoHoraInicio').value;
  const duracao = parseInt(document.getElementById('campoDuracao').value || '0', 10);
  if (!inicio || !duracao) return;
  const [h, m] = inicio.split(':').map(Number);
  const totalMin = h * 60 + m + duracao;
  const hFim = String(Math.floor(totalMin / 60) % 24).padStart(2, '0');
  const mFim = String(totalMin % 60).padStart(2, '0');
  document.getElementById('campoHoraFim').value = `${hFim}:${mFim}`;
}

document.getElementById('campoServico').addEventListener('change', recalcularDuracaoEValor);
document.getElementById('campoHoraInicio').addEventListener('change', recalcularHoraFim);

function abrirModalNovo(horaSugerida, profissionalIdSugerido) {
  agendamentoEmEdicaoId = 0;
  document.getElementById('modalTitulo').textContent = 'Novo agendamento';
  document.getElementById('campoData').value = document.getElementById('filtroData').value;
  document.getElementById('campoHoraInicio').value = horaSugerida || '';
  if (profissionalIdSugerido) document.getElementById('campoProfissional').value = profissionalIdSugerido;
  recalcularDuracaoEValor();
  mostrarEtapaForm();
  document.getElementById('modalAgendamento').classList.remove('hidden');
  document.getElementById('modalAgendamento').classList.add('flex');
}

async function abrirModalEdicao(id) {
  const res = await fetch(BASE + '?acao=detalhe&id=' + id);
  const json = await res.json();
  if (!json.ok) return alert('Agendamento não encontrado.');
  const ag = json.agendamento;

  agendamentoEmEdicaoId = ag.id;
  document.getElementById('modalTitulo').textContent = 'Editar agendamento';
  document.getElementById('campoServico').value = ag.servico_id;
  document.getElementById('campoProfissional').value = ag.profissional_id;
  document.getElementById('campoData').value = ag.data_atendimento;
  document.getElementById('campoHoraInicio').value = ag.hora_inicio;
  document.getElementById('campoHoraFim').value = ag.hora_fim;
  document.getElementById('campoDuracao').value = calcularDuracaoMin(ag.hora_inicio, ag.hora_fim);
  document.getElementById('campoValor').value = parseFloat(ag.valor).toFixed(2);

  mostrarEtapaForm();
  document.getElementById('modalAgendamento').classList.remove('hidden');
  document.getElementById('modalAgendamento').classList.add('flex');
}

function calcularDuracaoMin(inicio, fim) {
  const [h1, m1] = inicio.split(':').map(Number);
  const [h2, m2] = fim.split(':').map(Number);
  return (h2 * 60 + m2) - (h1 * 60 + m1);
}

function fecharModal() {
  document.getElementById('modalAgendamento').classList.add('hidden');
  document.getElementById('modalAgendamento').classList.remove('flex');
}

function mostrarEtapaForm() {
  document.getElementById('modalEtapaForm').classList.remove('hidden');
  document.getElementById('modalEtapaConfirmacao').classList.add('hidden');
  document.getElementById('modalErro').classList.add('hidden');
}

function irParaConfirmacao() {
  const servicoId = document.getElementById('campoServico').value;
  const profissionalId = document.getElementById('campoProfissional').value;
  const data = document.getElementById('campoData').value;
  const horaInicio = document.getElementById('campoHoraInicio').value;
  const horaFim = document.getElementById('campoHoraFim').value;
  const valor = document.getElementById('campoValor').value;

  if (!servicoId || !profissionalId || !data || !horaInicio || !valor) {
    const erro = document.getElementById('modalErro');
    erro.textContent = 'Preencha todos os campos obrigatórios.';
    erro.classList.remove('hidden');
    return;
  }

  const servicoNome = document.getElementById('campoServico').selectedOptions[0].textContent;
  const profNome = document.getElementById('campoProfissional').selectedOptions[0].textContent;

  document.getElementById('resumoServico').textContent = servicoNome;
  document.getElementById('resumoProfissional').textContent = profNome;
  document.getElementById('resumoData').textContent = formatarDataBR(data);
  document.getElementById('resumoHorario').textContent = `${horaInicio} às ${horaFim}`;
  document.getElementById('resumoValor').textContent = 'R$ ' + parseFloat(valor).toFixed(2).replace('.', ',');

  document.getElementById('modalEtapaForm').classList.add('hidden');
  document.getElementById('modalEtapaConfirmacao').classList.remove('hidden');
}

function voltarParaForm() {
  mostrarEtapaForm();
}

function formatarDataBR(data) {
  const [ano, mes, dia] = data.split('-');
  return `${dia}/${mes}/${ano}`;
}

async function confirmarSalvar() {
  const body = new URLSearchParams({
    acao: 'salvar',
    id: agendamentoEmEdicaoId,
    servico_id: document.getElementById('campoServico').value,
    profissional_id: document.getElementById('campoProfissional').value,
    data_atendimento: document.getElementById('campoData').value,
    hora_inicio: document.getElementById('campoHoraInicio').value,
    hora_fim: document.getElementById('campoHoraFim').value,
    valor: document.getElementById('campoValor').value,
  });

  const res = await fetch(BASE, { method: 'POST', body });
  const json = await res.json();

  if (!json.ok) {
    const erro = document.getElementById('confirmacaoErro');
    erro.textContent = json.erro;
    erro.classList.remove('hidden');
    return;
  }

  fecharModal();
  carregarAgenda();
}

async function carregarAgenda() {
  const data = document.getElementById('filtroData').value;
  const res = await fetch(BASE + '?acao=listar&data=' + data);
  const json = await res.json();
  const container = document.getElementById('gradeHorarios');

  if (!json.agendamentos.length) {
    container.innerHTML = `
      <p class="text-sm text-gray-400 mb-4">Nenhum agendamento para esta data.</p>
      <button onclick="abrirModalNovo('09:00')" class="text-sm bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
        + Novo agendamento
      </button>
    `;
    return;
  }

  container.innerHTML = `
    <div class="flex justify-end mb-3">
      <button onclick="abrirModalNovo('09:00')" class="text-sm bg-purple-600 text-white px-4 py-2 rounded hover:bg-purple-700">
        + Novo agendamento
      </button>
    </div>
    <table class="w-full text-sm">
      <thead>
        <tr class="text-left text-gray-500 border-b">
          <th class="py-2 pr-4">Horário</th>
          <th class="py-2 pr-4">Profissional</th>
          <th class="py-2 pr-4">Serviço</th>
          <th class="py-2 pr-4">Valor</th>
          <th class="py-2 pr-4">Criado por</th>
          <th class="py-2 pr-4">Status</th>
        </tr>
      </thead>
      <tbody>
        ${json.agendamentos.map(a => `
          <tr class="border-b last:border-0 cursor-pointer hover:bg-purple-50" onclick="abrirModalEdicao(${a.id})">
            <td class="py-2 pr-4 font-medium text-gray-800">${a.hora_inicio} - ${a.hora_fim}</td>
            <td class="py-2 pr-4 text-gray-600">${a.profissional_nome}</td>
            <td class="py-2 pr-4 text-gray-600">${a.servico_nome}</td>
            <td class="py-2 pr-4 text-gray-600">R$ ${parseFloat(a.valor).toFixed(2).replace('.', ',')}</td>
            <td class="py-2 pr-4 text-gray-400 text-xs">${a.criado_por_nome}${a.atualizado_por_nome ? ' (editado por ' + a.atualizado_por_nome + ')' : ''}</td>
            <td class="py-2 pr-4 text-gray-600">${a.status}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

document.getElementById('filtroData').addEventListener('change', carregarAgenda);

carregarOpcoes().then(carregarAgenda);
</script>

</body>
</html>
