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
$usuarioNome = (string) ($_SESSION['nome'] ?? '');
$podeGerenciarTudo = $tipoUsuario === 'owner';
$meuProfissionalId = null;

if ($tipoUsuario === 'owner') {
    exigirOwner();
    $companyId = companyIdDoOwner();
    $usuarioId = (int) ($_SESSION['owner_id'] ?? 0);
} else {
    $companyId = (int) ($_SESSION['company_id'] ?? 0);
    $usuarioId = (int) ($_SESSION['profissional_id'] ?? 0);
    $meuProfissionalId = $usuarioId;

    $stmtPerm = $pdo->prepare('
        SELECT pode_gerenciar_agenda_geral
        FROM profissionais
        WHERE id = ? AND company_id = ? AND status = "ativo"
    ');
    $stmtPerm->execute([$usuarioId, $companyId]);
    $permissao = $stmtPerm->fetchColumn();
    if ($permissao === false) {
        $_SESSION = [];
        header('Location: /profissional/login.php');
        exit;
    }
    $podeGerenciarTudo = (bool) $permissao;
}

function responderJson(array $resposta, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($resposta);
    exit;
}

function dataIsoValida(string $data): bool
{
    $valor = DateTime::createFromFormat('!Y-m-d', $data);
    $erros = DateTime::getLastErrors();
    return $valor !== false
        && ($erros === false || ($erros['warning_count'] === 0 && $erros['error_count'] === 0))
        && $valor->format('Y-m-d') === $data;
}

function existeConflito(PDO $pdo, int $companyId, int $profissionalId, string $inicio, string $fim, int $ignorarId = 0): bool
{
    $stmt = $pdo->prepare('
        SELECT id
        FROM agendamentos
        WHERE company_id = ?
          AND profissional_id = ?
          AND status <> "cancelado"
          AND id <> ?
          AND data_hora_inicio < ?
          AND data_hora_fim > ?
        LIMIT 1
        FOR UPDATE
    ');
    $stmt->execute([$companyId, $profissionalId, $ignorarId, $fim, $inicio]);
    return $stmt->fetchColumn() !== false;
}

if (isset($_GET['acao']) && $_GET['acao'] === 'listar') {
    $data = (string) ($_GET['data'] ?? date('Y-m-d'));
    if (!dataIsoValida($data)) {
        responderJson(['ok' => false, 'erro' => 'Data inválida.'], 400);
    }

    $inicioDia = $data . ' 00:00:00';
    $fimDia = date('Y-m-d 00:00:00', strtotime($data . ' +1 day'));
    $stmt = $pdo->prepare('
        SELECT a.*, s.nome AS servico_nome, s.preco AS valor,
               p.nome AS profissional_nome, c.nome_completo AS cliente_nome,
               DATE_FORMAT(a.data_hora_inicio, "%Y-%m-%d") AS data_atendimento,
               DATE_FORMAT(a.data_hora_inicio, "%H:%i") AS hora_inicio,
               DATE_FORMAT(a.data_hora_fim, "%H:%i") AS hora_fim
        FROM agendamentos a
        JOIN servicos s ON s.id = a.servico_id AND s.company_id = a.company_id
        JOIN profissionais p ON p.id = a.profissional_id AND p.company_id = a.company_id
        JOIN clientes c ON c.id = a.cliente_id AND c.company_id = a.company_id
        WHERE a.company_id = ? AND a.data_hora_inicio >= ? AND a.data_hora_inicio < ?
        ORDER BY a.data_hora_inicio
    ');
    $stmt->execute([$companyId, $inicioDia, $fimDia]);
    responderJson(['ok' => true, 'agendamentos' => $stmt->fetchAll()]);
}

if (isset($_GET['acao']) && $_GET['acao'] === 'opcoes') {
    $servicos = $pdo->prepare('
        SELECT id, nome, duracao_min, preco
        FROM servicos
        WHERE company_id = ? AND status = "ativo"
        ORDER BY nome
    ');
    $servicos->execute([$companyId]);

    $profissionais = $pdo->prepare('
        SELECT id, nome
        FROM profissionais
        WHERE company_id = ? AND status = "ativo"
        ORDER BY nome
    ');
    $profissionais->execute([$companyId]);

    responderJson([
        'ok' => true,
        'servicos' => $servicos->fetchAll(),
        'profissionais' => $profissionais->fetchAll(),
        'podeGerenciarTudo' => $podeGerenciarTudo,
        'meuProfissionalId' => $meuProfissionalId,
        'csrfToken' => tokenCsrf(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'salvar') {
    header('Content-Type: application/json; charset=utf-8');
    if (!validarTokenCsrf()) {
        responderJson(['ok' => false, 'erro' => 'Sessão expirada. Atualize a página e tente novamente.'], 403);
    }

    $id = (int) ($_POST['id'] ?? 0);
    $servicoId = (int) ($_POST['servico_id'] ?? 0);
    $profissionalId = (int) ($_POST['profissional_id'] ?? 0);
    $data = trim((string) ($_POST['data_atendimento'] ?? ''));
    $horaInicio = trim((string) ($_POST['hora_inicio'] ?? ''));
    $nomeCliente = trim((string) ($_POST['cliente_nome'] ?? ''));
    $whatsapp = trim((string) ($_POST['cliente_whatsapp'] ?? ''));
    $nascimento = trim((string) ($_POST['cliente_nascimento'] ?? ''));
    $whatsapp = preg_replace('/\D+/', '', $whatsapp);

    if ($tipoUsuario === 'profissional' && !$podeGerenciarTudo && $profissionalId !== $meuProfissionalId) {
        responderJson(['ok' => false, 'erro' => 'Você não tem permissão para gerenciar a agenda de outro profissional.'], 403);
    }

    if (
        $id < 0 || $servicoId < 1 || $profissionalId < 1
        || !dataIsoValida($data)
        || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $horaInicio)
        || $nomeCliente === '' || strlen($nomeCliente) > 150
        || strlen($whatsapp) < 8 || strlen($whatsapp) > 20
        || !dataIsoValida($nascimento) || $nascimento > date('Y-m-d')
    ) {
        responderJson(['ok' => false, 'erro' => 'Revise os dados do agendamento e do cliente.'], 400);
    }

    $stmtServico = $pdo->prepare('
        SELECT duracao_min, status
        FROM servicos
        WHERE id = ? AND company_id = ?
    ');
    $stmtServico->execute([$servicoId, $companyId]);
    $servico = $stmtServico->fetch();
    if (!$servico || (int) $servico['duracao_min'] < 1) {
        responderJson(['ok' => false, 'erro' => 'Serviço inválido ou inativo.'], 400);
    }
    if ($servico['status'] !== 'ativo') {
        $stmtServicoUsado = $pdo->prepare('
            SELECT id FROM agendamentos
            WHERE id = ? AND company_id = ? AND servico_id = ?
        ');
        $stmtServicoUsado->execute([$id, $companyId, $servicoId]);
        if ($id === 0 || !$stmtServicoUsado->fetchColumn()) {
            responderJson(['ok' => false, 'erro' => 'Serviço inválido ou inativo.'], 400);
        }
    }
    $duracao = (int) $servico['duracao_min'];

    $stmtProfissional = $pdo->prepare('
        SELECT id FROM profissionais
        WHERE id = ? AND company_id = ? AND status = "ativo"
    ');
    $stmtProfissional->execute([$profissionalId, $companyId]);
    if (!$stmtProfissional->fetchColumn()) {
        responderJson(['ok' => false, 'erro' => 'Profissional inválido ou inativo.'], 400);
    }

    $inicio = DateTime::createFromFormat('!Y-m-d H:i', $data . ' ' . $horaInicio);
    $fim = clone $inicio;
    $fim->modify('+' . $duracao . ' minutes');
    if ($fim->format('Y-m-d') !== $data) {
        responderJson(['ok' => false, 'erro' => 'O horário do serviço ultrapassa o fim do dia.'], 400);
    }
    $inicioBanco = $inicio->format('Y-m-d H:i:s');
    $fimBanco = $fim->format('Y-m-d H:i:s');

    try {
        $pdo->beginTransaction();

        if ($id > 0) {
            $stmtAtual = $pdo->prepare('SELECT id FROM agendamentos WHERE id = ? AND company_id = ? FOR UPDATE');
            $stmtAtual->execute([$id, $companyId]);
            if (!$stmtAtual->fetchColumn()) {
                $pdo->rollBack();
                responderJson(['ok' => false, 'erro' => 'Agendamento não encontrado.'], 404);
            }
        }

        if (existeConflito($pdo, $companyId, $profissionalId, $inicioBanco, $fimBanco, $id)) {
            $pdo->rollBack();
            responderJson(['ok' => false, 'erro' => 'Este horário já está ocupado para o profissional selecionado.'], 409);
        }

        $whatsapp = preg_replace('/\s+/', '', $whatsapp);
        $stmtCliente = $pdo->prepare('
            SELECT id FROM clientes
            WHERE company_id = ? AND whatsapp = ?
            FOR UPDATE
        ');
        $stmtCliente->execute([$companyId, $whatsapp]);
        $clienteId = (int) $stmtCliente->fetchColumn();

        if ($clienteId === 0) {
            $stmtCliente = $pdo->prepare('
                INSERT INTO clientes (company_id, whatsapp, nome_completo, data_nascimento, cadastro_completo)
                VALUES (?, ?, ?, ?, 1)
            ');
            $stmtCliente->execute([$companyId, $whatsapp, $nomeCliente, $nascimento]);
            $clienteId = (int) $pdo->lastInsertId();
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('
                UPDATE agendamentos SET
                    servico_id = ?, profissional_id = ?, cliente_id = ?,
                    data_hora_inicio = ?, data_hora_fim = ?,
                    atualizado_por_tipo = ?, atualizado_por_id = ?, atualizado_por_nome = ?, atualizado_em = NOW()
                WHERE id = ? AND company_id = ?
            ');
            $stmt->execute([
                $servicoId, $profissionalId, $clienteId, $inicioBanco, $fimBanco,
                $tipoUsuario, $usuarioId, $usuarioNome, $id, $companyId,
            ]);
            $mensagem = 'Agendamento atualizado com sucesso.';
        } else {
            $stmt = $pdo->prepare('
                INSERT INTO agendamentos
                    (company_id, profissional_id, servico_id, cliente_id, data_hora_inicio, data_hora_fim,
                     status, criado_por_tipo, criado_por_id, criado_por_nome)
                VALUES (?, ?, ?, ?, ?, ?, "agendado", ?, ?, ?)
            ');
            $stmt->execute([
                $companyId, $profissionalId, $servicoId, $clienteId, $inicioBanco, $fimBanco,
                $tipoUsuario, $usuarioId, $usuarioNome,
            ]);
            $mensagem = 'Agendamento criado com sucesso.';
        }

        $pdo->commit();
        responderJson(['ok' => true, 'mensagem' => $mensagem]);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erro ao salvar agendamento: ' . $e->getMessage());
        responderJson(['ok' => false, 'erro' => 'Erro ao salvar. Tente novamente.'], 500);
    }
}

if (isset($_GET['acao']) && $_GET['acao'] === 'detalhe') {
    $id = (int) ($_GET['id'] ?? 0);
    $stmt = $pdo->prepare('
        SELECT a.*, s.nome AS servico_nome, s.duracao_min, s.preco AS valor,
               DATE_FORMAT(a.data_hora_inicio, "%Y-%m-%d") AS data_atendimento,
               DATE_FORMAT(a.data_hora_inicio, "%H:%i") AS hora_inicio,
               DATE_FORMAT(a.data_hora_fim, "%H:%i") AS hora_fim,
               c.nome_completo AS cliente_nome, c.whatsapp AS cliente_whatsapp,
               c.data_nascimento AS cliente_nascimento
        FROM agendamentos a
        JOIN servicos s ON s.id = a.servico_id AND s.company_id = a.company_id
        JOIN clientes c ON c.id = a.cliente_id AND c.company_id = a.company_id
        WHERE a.id = ? AND a.company_id = ?
    ');
    $stmt->execute([$id, $companyId]);
    $agendamento = $stmt->fetch();
    responderJson(['ok' => (bool) $agendamento, 'agendamento' => $agendamento], $agendamento ? 200 : 404);
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
  <div class="flex items-center gap-4">
    <?php if ($tipoUsuario === 'profissional'): ?>
      <a href="/profissional/perfil.php" class="text-sm text-purple-600 hover:underline">Meu perfil</a>
    <?php endif; ?>
    <span class="text-sm text-gray-600">Logado como <?= htmlspecialchars($usuarioNome) ?> (<?= $tipoUsuario ?>)</span>
    <a href="/includes/logout.php" class="text-sm text-red-600 hover:underline">Sair</a>
  </div>
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
        <input type="number" step="0.01" id="campoValor" class="w-full border rounded px-3 py-2 text-sm bg-gray-50" readonly>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Nome do cliente *</label>
        <input type="text" id="campoClienteNome" maxlength="150" class="w-full border rounded px-3 py-2 text-sm" required>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">WhatsApp do cliente *</label>
        <input type="tel" id="campoClienteWhatsapp" maxlength="20" class="w-full border rounded px-3 py-2 text-sm" required>
      </div>
      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Data de nascimento *</label>
        <input type="date" id="campoClienteNascimento" class="w-full border rounded px-3 py-2 text-sm" required>
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
let csrfToken = '';

const hoje = new Date();
hoje.setMinutes(hoje.getMinutes() - hoje.getTimezoneOffset());
document.getElementById('filtroData').value = hoje.toISOString().slice(0, 10);

function escaparHtml(valor) {
  return String(valor).replace(/[&<>"']/g, caractere => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  })[caractere]);
}

async function carregarOpcoes() {
  const res = await fetch(BASE + '?acao=opcoes');
  const json = await res.json();
  if (!res.ok || !json.ok) throw new Error(json.erro || 'Não foi possível carregar as opções.');
  servicosDisponiveis = json.servicos;
  profissionaisDisponiveis = json.profissionais;
  csrfToken = json.csrfToken;

  const selServico = document.getElementById('campoServico');
  selServico.innerHTML = servicosDisponiveis.map(s =>
    `<option value="${Number(s.id)}" data-duracao="${Number(s.duracao_min)}" data-preco="${Number(s.preco)}">${escaparHtml(s.nome)}</option>`
  ).join('');

  const selProf = document.getElementById('campoProfissional');
  selProf.innerHTML = profissionaisDisponiveis.map(p =>
    `<option value="${Number(p.id)}" ${(!podeGerenciarTudo && Number(p.id) !== Number(meuProfissionalId)) ? 'disabled' : ''}>${escaparHtml(p.nome)}${(!podeGerenciarTudo && Number(p.id) !== Number(meuProfissionalId)) ? ' (sem permissão)' : ''}</option>`
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
  document.getElementById('campoClienteNome').value = '';
  document.getElementById('campoClienteWhatsapp').value = '';
  document.getElementById('campoClienteNascimento').value = '';
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
  if (!Array.from(document.getElementById('campoServico').options).some(opcao => Number(opcao.value) === Number(ag.servico_id))) {
    const opcao = document.createElement('option');
    opcao.value = ag.servico_id;
    opcao.textContent = ag.servico_nome;
    opcao.dataset.duracao = ag.duracao_min;
    opcao.dataset.preco = ag.valor;
    document.getElementById('campoServico').appendChild(opcao);
  }
  document.getElementById('campoServico').value = ag.servico_id;
  document.getElementById('campoProfissional').value = ag.profissional_id;
  document.getElementById('campoData').value = ag.data_atendimento;
  document.getElementById('campoHoraInicio').value = ag.hora_inicio;
  document.getElementById('campoHoraFim').value = ag.hora_fim;
  document.getElementById('campoDuracao').value = calcularDuracaoMin(ag.hora_inicio, ag.hora_fim);
  document.getElementById('campoValor').value = parseFloat(ag.valor).toFixed(2);
  document.getElementById('campoClienteNome').value = ag.cliente_nome;
  document.getElementById('campoClienteWhatsapp').value = ag.cliente_whatsapp;
  document.getElementById('campoClienteNascimento').value = ag.cliente_nascimento;

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
  const clienteNome = document.getElementById('campoClienteNome').value.trim();
  const clienteWhatsapp = document.getElementById('campoClienteWhatsapp').value.trim();
  const clienteNascimento = document.getElementById('campoClienteNascimento').value;

  if (!servicoId || !profissionalId || !data || !horaInicio || !horaFim || !valor
      || !clienteNome || !clienteWhatsapp || !clienteNascimento) {
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
    cliente_nome: document.getElementById('campoClienteNome').value.trim(),
    cliente_whatsapp: document.getElementById('campoClienteWhatsapp').value.trim(),
    cliente_nascimento: document.getElementById('campoClienteNascimento').value,
    csrf_token: csrfToken,
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
          <th class="py-2 pr-4">Cliente</th>
          <th class="py-2 pr-4">Valor</th>
          <th class="py-2 pr-4">Criado por</th>
          <th class="py-2 pr-4">Status</th>
        </tr>
      </thead>
      <tbody>
        ${json.agendamentos.map(a => `
          <tr class="border-b last:border-0 cursor-pointer hover:bg-purple-50" onclick="abrirModalEdicao(${a.id})">
            <td class="py-2 pr-4 font-medium text-gray-800">${escaparHtml(a.hora_inicio)} - ${escaparHtml(a.hora_fim)}</td>
            <td class="py-2 pr-4 text-gray-600">${escaparHtml(a.profissional_nome)}</td>
            <td class="py-2 pr-4 text-gray-600">${escaparHtml(a.servico_nome)}</td>
            <td class="py-2 pr-4 text-gray-600">${escaparHtml(a.cliente_nome)}</td>
            <td class="py-2 pr-4 text-gray-600">R$ ${parseFloat(a.valor).toFixed(2).replace('.', ',')}</td>
            <td class="py-2 pr-4 text-gray-400 text-xs">${escaparHtml(a.criado_por_nome)}${a.atualizado_por_nome ? ' (editado por ' + escaparHtml(a.atualizado_por_nome) + ')' : ''}</td>
            <td class="py-2 pr-4 text-gray-600">${escaparHtml(a.status)}</td>
          </tr>
        `).join('')}
      </tbody>
    </table>
  `;
}

document.getElementById('filtroData').addEventListener('change', carregarAgenda);

carregarOpcoes().then(carregarAgenda).catch(erro => {
  document.getElementById('gradeHorarios').textContent = erro.message;
});
</script>

</body>
</html>
