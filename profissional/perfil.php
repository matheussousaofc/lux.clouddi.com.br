<?php
/**
 * Tela de perfil do profissional — todos os campos são opcionais e podem
 * ser editados a qualquer momento após o login.
 */
require_once __DIR__ . '/../includes/auth.php';

iniciarSessao();
if (($_SESSION['tipo'] ?? '') !== 'profissional') {
    header('Location: /profissional/login.php');
    exit;
}

$pdo = getDB();
$profissionalId = (int) $_SESSION['profissional_id'];
$companyId = (int) ($_SESSION['company_id'] ?? 0);
$erro = '';
$sucesso = '';

// Pasta de upload de fotos
$pastaUploads = __DIR__ . '/../uploads/profissionais/';
if (!is_dir($pastaUploads)) {
    if (!mkdir($pastaUploads, 0755, true) && !is_dir($pastaUploads)) {
        error_log('Não foi possível criar a pasta de fotos dos profissionais.');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirTokenCsrf();
    $cpf = trim($_POST['cpf'] ?? '');
    $emailContato = trim($_POST['email'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');

    if ($emailContato !== '' && !filter_var($emailContato, FILTER_VALIDATE_EMAIL)) {
        $erro = 'E-mail inválido.';
    } else {
        // Busca foto atual, para manter se não enviar nova e não houver remoção
        $stmtAtual = $pdo->prepare('SELECT foto_url FROM profissionais WHERE id = ? AND company_id = ? AND status = "ativo"');
        $stmtAtual->execute([$profissionalId, $companyId]);
        $fotoAtual = $stmtAtual->fetchColumn();
        if ($fotoAtual === false) {
            http_response_code(404);
            exit('Perfil não encontrado.');
        }
        $fotoUrl = $fotoAtual;

        // Remover foto, se solicitado
        if (isset($_POST['remover_foto']) && $_POST['remover_foto'] === '1') {
            $fotoUrl = null;
        }

        // Upload de nova foto, se enviada
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
            $extensao = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));

            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
                $erro = 'Não foi possível receber a imagem enviada.';
            } elseif (!in_array($extensao, $extensoesPermitidas, true)) {
                $erro = 'Formato de imagem não suportado. Use JPG, PNG ou WEBP.';
            } elseif ($_FILES['foto']['size'] > 3 * 1024 * 1024) {
                $erro = 'A imagem deve ter no máximo 3MB.';
            } else {
                $imagem = getimagesize($_FILES['foto']['tmp_name']);
                $mimesPermitidos = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                ];
                if ($imagem === false || $imagem['mime'] !== $mimesPermitidos[$extensao]) {
                    $erro = 'O arquivo enviado não é uma imagem válida.';
                } else {
                    $nomeArquivo = 'prof_' . $profissionalId . '_' . bin2hex(random_bytes(8)) . '.' . $extensao;
                    $destino = $pastaUploads . $nomeArquivo;

                    if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                        $fotoUrl = 'uploads/profissionais/' . $nomeArquivo;
                    } else {
                        $erro = 'Erro ao enviar a imagem. Tente novamente.';
                    }
                }
            }
        }

        if ($erro === '') {
            $stmt = $pdo->prepare('
                UPDATE profissionais
                SET cpf = ?, email_contato = ?, endereco = ?, foto_url = ?
                WHERE id = ? AND company_id = ?
            ');
            $stmt->execute([
                $cpf !== '' ? $cpf : null,
                $emailContato !== '' ? $emailContato : null,
                $endereco !== '' ? $endereco : null,
                $fotoUrl,
                $profissionalId,
                $companyId,
            ]);
            $sucesso = 'Perfil atualizado com sucesso.';
        }
    }
}

$stmt = $pdo->prepare('SELECT nome, cpf, email_contato, endereco, foto_url FROM profissionais WHERE id = ? AND company_id = ?');
$stmt->execute([$profissionalId, $companyId]);
$profissional = $stmt->fetch();
if (!$profissional) {
    http_response_code(404);
    exit('Perfil não encontrado.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Meu perfil</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen">

<header class="bg-white border-b px-6 py-4 flex items-center justify-between">
  <h1 class="text-lg font-bold text-gray-800">Meu perfil</h1>
  <a href="/owner/agenda.php" class="text-sm text-purple-600 hover:underline">Voltar para a agenda</a>
</header>

<main class="max-w-md mx-auto p-6">
  <div class="bg-white rounded-xl shadow p-6 space-y-4">
    <p class="text-sm text-gray-500">
      Olá, <strong><?= htmlspecialchars($profissional['nome']) ?></strong>. Todos os campos abaixo são opcionais
      e podem ser editados quando quiser.
    </p>

    <?php if ($erro): ?>
      <p class="text-sm text-red-600 bg-red-50 border border-red-200 rounded px-3 py-2"><?= htmlspecialchars($erro) ?></p>
    <?php endif; ?>
    <?php if ($sucesso): ?>
      <p class="text-sm text-green-700 bg-green-50 border border-green-200 rounded px-3 py-2"><?= htmlspecialchars($sucesso) ?></p>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-4">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">

      <div class="flex items-center gap-4">
        <div class="w-16 h-16 rounded-full bg-gray-100 overflow-hidden flex items-center justify-center border">
          <?php if (!empty($profissional['foto_url'])): ?>
            <img src="/<?= htmlspecialchars($profissional['foto_url']) ?>" class="w-full h-full object-cover" alt="Foto do perfil">
          <?php else: ?>
            <span class="text-xs text-gray-400">Sem foto</span>
          <?php endif; ?>
        </div>
        <div class="flex-1">
          <label class="block text-xs font-medium text-gray-600 mb-1">Foto (opcional)</label>
          <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" class="text-xs w-full">
          <?php if (!empty($profissional['foto_url'])): ?>
            <label class="flex items-center gap-1 mt-1 text-xs text-gray-500">
              <input type="checkbox" name="remover_foto" value="1"> Remover foto atual
            </label>
          <?php endif; ?>
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">CPF (opcional)</label>
        <input type="text" name="cpf" value="<?= htmlspecialchars($profissional['cpf'] ?? '') ?>"
               placeholder="000.000.000-00" class="w-full border rounded px-3 py-2 text-sm">
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">E-mail (opcional)</label>
        <input type="email" name="email" value="<?= htmlspecialchars($profissional['email_contato'] ?? '') ?>"
               placeholder="voce@email.com" class="w-full border rounded px-3 py-2 text-sm">
      </div>

      <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">Endereço (opcional)</label>
        <input type="text" name="endereco" value="<?= htmlspecialchars($profissional['endereco'] ?? '') ?>"
               placeholder="Rua, número, bairro, cidade" class="w-full border rounded px-3 py-2 text-sm">
      </div>

      <div class="flex justify-end pt-2">
        <button type="submit" class="px-4 py-2 text-sm bg-purple-600 text-white rounded hover:bg-purple-700">
          Salvar alterações
        </button>
      </div>
    </form>
  </div>
</main>

</body>
</html>
