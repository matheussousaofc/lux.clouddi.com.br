<?php
require_once __DIR__ . '/../includes/auth.php';
iniciarSessao();

// Só exige estar logado como owner (não exige empresa, pois é esta tela que a cria)
if (($_SESSION['tipo'] ?? '') !== 'owner') {
    header('Location: /owner/login.php');
    exit;
}

// Se já tem empresa, não precisa cadastrar de novo
if (!empty($_SESSION['company_id'])) {
    header('Location: /owner/dashboard.php');
    exit;
}

$pdo = getDB();
$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirTokenCsrf();
    $nome     = trim($_POST['nome'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $cpf      = trim($_POST['cpf'] ?? '');
    $status   = $_POST['status'] ?? 'ativa';

    if ($nome === '' || strlen($nome) > 160) {
        $erro = 'Informe o nome da empresa.';
    } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido para a empresa.';
    } elseif (!in_array($status, ['ativa', 'atrasada', 'suspensa', 'cancelada'], true)) {
        $erro = 'Status da empresa inválido.';
    } else {
        $fotoPath = null;

        if (!empty($_FILES['foto']['name'])) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $permitidas = ['jpg', 'jpeg', 'png', 'webp'];
            $erroUpload = $_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($erroUpload !== UPLOAD_ERR_OK) {
                $erro = 'Não foi possível receber a imagem enviada.';
            } elseif ($_FILES['foto']['size'] > 3 * 1024 * 1024) {
                $erro = 'A imagem deve ter no máximo 3MB.';
            } elseif (!in_array($ext, $permitidas, true)) {
                $erro = 'Formato de imagem não suportado. Use JPG, PNG ou WEBP.';
            } else {
                $imagem = getimagesize($_FILES['foto']['tmp_name']);
                $mimesPermitidos = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                ];
                if ($imagem === false || $imagem['mime'] !== $mimesPermitidos[$ext]) {
                    $erro = 'O arquivo enviado não é uma imagem válida.';
                }
            }

            if ($erro === '') {
                $destDir = __DIR__ . '/../uploads/empresas/';
                if (!is_dir($destDir)) {
                    if (!mkdir($destDir, 0755, true) && !is_dir($destDir)) {
                        error_log('Não foi possível criar a pasta de fotos das empresas.');
                    }
                }
                $nomeArquivo = 'empresa_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (is_dir($destDir) && move_uploaded_file($_FILES['foto']['tmp_name'], $destDir . $nomeArquivo)) {
                    $fotoPath = '/uploads/empresas/' . $nomeArquivo;
                } else {
                    $erro = 'Não foi possível salvar a imagem enviada.';
                }
            }
        }

        if ($erro === '') {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare('
                    INSERT INTO companies (nome_fantasia, telefone, email_contato, foto, cpf, status_mensalidade)
                    VALUES (?, ?, ?, ?, ?, ?)
                ');
                $stmt->execute([$nome, $telefone ?: null, $email ?: null, $fotoPath, $cpf ?: null, $status]);
                $companyId = (int) $pdo->lastInsertId();

                $stmtSettings = $pdo->prepare('
                    INSERT INTO company_settings (company_id, intervalo_agendamento_min, antecedencia_minima_min)
                    VALUES (?, 30, 0)
                ');
                $stmtSettings->execute([$companyId]);

                $stmtOwner = $pdo->prepare('UPDATE owner_users SET company_id = ? WHERE id = ?');
                $stmtOwner->execute([$companyId, $_SESSION['owner_id']]);

                $pdo->commit();

                $_SESSION['company_id'] = $companyId;
                header('Location: /owner/dashboard.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Erro ao cadastrar empresa: ' . $e->getMessage());
                $erro = 'Erro ao salvar. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Cadastrar empresa - Lux ERP</title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
<div class="bg-white shadow rounded-xl p-8 w-full max-w-lg">
  <h1 class="text-lg font-bold text-gray-800 mb-1">Finalize seu cadastro</h1>
  <p class="text-sm text-gray-500 mb-6">Precisamos dos dados da sua empresa para liberar o acesso completo.</p>

  <?php if ($erro): ?>
    <div class="bg-red-100 text-red-700 text-sm p-3 rounded mb-4"><?= htmlspecialchars($erro) ?></div>
  <?php endif; ?>

  <form method="POST" enctype="multipart/form-data" class="space-y-4">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') ?>">
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Nome da empresa *</label>
      <input type="text" name="nome" required class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Telefone</label>
      <input type="text" name="telefone" class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">E-mail</label>
      <input type="email" name="email" class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">CPF</label>
      <input type="text" name="cpf" maxlength="14" placeholder="000.000.000-00" class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Foto / Logo</label>
      <input type="file" name="foto" accept=".jpg,.jpeg,.png,.webp" class="w-full border rounded px-3 py-2">
    </div>
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
      <select name="status" class="w-full border rounded px-3 py-2">
        <option value="ativa">Ativa</option>
        <option value="atrasada">Atrasada</option>
        <option value="suspensa">Suspensa</option>
        <option value="cancelada">Cancelada</option>
      </select>
    </div>
    <button type="submit" class="w-full bg-purple-600 text-white font-medium py-2 rounded hover:bg-purple-700 transition">
      Concluir cadastro
    </button>
  </form>
</div>
</body>
</html>
