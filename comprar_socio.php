<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = currentUser();
$flash = getFlash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? 'activate');

    if ($action === 'code_login') {
        redirect('codigo_login.php');
    }

    $name = trim((string) ($_POST['name'] ?? ''));
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $phone = trim((string) ($_POST['phone'] ?? ''));
    $cpf = normalizeCpf((string) ($_POST['cpf'] ?? ''));
    $affiliationType = trim((string) ($_POST['affiliation_type'] ?? ''));
    $courseName = trim((string) ($_POST['course_name'] ?? ''));

    $validAffiliations = ['aluno', 'egresso', 'externo'];
    $requiresCourse = $affiliationType === 'aluno';

    if (
        $name === '' ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) ||
        strlen(preg_replace('/\D+/', '', $phone) ?? '') < 10 ||
        !isValidCpf($cpf) ||
        !in_array($affiliationType, $validAffiliations, true) ||
        ($requiresCourse && $courseName === '')
    ) {
        setFlash('error', 'Revise os dados: nome, email válido, telefone (mín. 10 dígitos), CPF válido e vínculo (com curso quando necessário).');
        redirect('comprar_socio.php');
    }

    $pdo = db();

    // Impede duplicidade de compra por dados já usados.
    $dupStmt = $pdo->prepare(
        'SELECT u.id, u.name, u.email
         FROM users u
         INNER JOIN memberships m ON m.user_id = u.id
         WHERE lower(u.email) = :email
            OR u.cpf = :cpf
            OR (u.name = :name AND lower(u.email) = :email)
         ORDER BY m.created_at DESC
         LIMIT 1'
    );
    $dupStmt->execute([
        ':email' => $email,
        ':cpf' => $cpf,
        ':name' => $name,
    ]);
    $dup = $dupStmt->fetch();

    if ($dup && (!$user || (int) $dup['id'] !== (int) $user['id'])) {
        setFlash('error', 'Já existe compra registrada com esses dados (nome/email/CPF). Se já é sócio, use o login por código.');
        redirect('comprar_socio.php');
    }

    $pdo->beginTransaction();

    try {
        $userId = null;

        if ($user) {
            $userId = (int) $user['id'];
            $update = $pdo->prepare('UPDATE users SET name = :name, email = :email, phone = :phone, cpf = :cpf, affiliation_type = :affiliation_type, course_name = :course_name WHERE id = :id');
            $update->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':cpf' => $cpf,
                ':affiliation_type' => $affiliationType,
                ':course_name' => $requiresCourse ? $courseName : null,
                ':id' => $userId,
            ]);
        } else {
            $existing = $pdo->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
            $existing->execute([':email' => $email]);
            $found = $existing->fetch();

            if ($found) {
                $userId = (int) $found['id'];
                $update = $pdo->prepare('UPDATE users SET name = :name, phone = :phone, cpf = :cpf, affiliation_type = :affiliation_type, course_name = :course_name WHERE id = :id');
                $update->execute([
                    ':name' => $name,
                    ':phone' => $phone,
                    ':cpf' => $cpf,
                    ':affiliation_type' => $affiliationType,
                    ':course_name' => $requiresCourse ? $courseName : null,
                    ':id' => $userId,
                ]);
            } else {
                $randomPassword = bin2hex(random_bytes(8));
                $insertUser = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, phone, cpf, affiliation_type, course_name, created_at)
                     VALUES (:name, :email, :hash, :phone, :cpf, :affiliation_type, :course_name, :created)'
                );
                $insertUser->execute([
                    ':name' => $name,
                    ':email' => $email,
                    ':hash' => password_hash($randomPassword, PASSWORD_DEFAULT),
                    ':phone' => $phone,
                    ':cpf' => $cpf,
                    ':affiliation_type' => $affiliationType,
                    ':course_name' => $requiresCourse ? $courseName : null,
                    ':created' => nowIso(),
                ]);
                $userId = (int) $pdo->lastInsertId();
            }
        }

        // Uma compra por cadastro (bloqueia nova ativação para quem já comprou antes).
        $existingPurchaseStmt = $pdo->prepare('SELECT COUNT(*) FROM memberships WHERE user_id = :uid');
        $existingPurchaseStmt->execute([':uid' => $userId]);
        $hasAnyPurchase = (int) $existingPurchaseStmt->fetchColumn() > 0;
        if ($hasAnyPurchase) {
            $_SESSION['user_id'] = $userId;
            $pdo->commit();
            setFlash('error', 'Este cadastro já possui compra registrada. Use o login por código de sócio.');
            redirect('codigo_login.php');
        }

        $start = new DateTimeImmutable(todayDate());
        $end = $start->modify('+1 year')->modify('-1 day');
        $code = generateMemberCode();

        $insertMembership = $pdo->prepare(
            'INSERT INTO memberships (user_id, status, member_code, start_date, end_date, created_at)
             VALUES (:uid, :status, :code, :start, :end, :created)'
        );
        $insertMembership->execute([
            ':uid' => $userId,
            ':status' => 'active',
            ':code' => $code,
            ':start' => $start->format('Y-m-d'),
            ':end' => $end->format('Y-m-d'),
            ':created' => nowIso(),
        ]);
        $membershipId = (int) $pdo->lastInsertId();

        $receiptCode = makeReceiptCode();
        $issuedAt = nowIso();
        $secureHash = hash('sha256', $userId . '|' . $membershipId . '|' . $issuedAt . '|' . $receiptCode . '|' . RECEIPT_SECRET);
        $receiptStmt = $pdo->prepare(
            'INSERT INTO receipts (membership_id, user_id, receipt_code, amount_cents, issued_at, secure_hash)
             VALUES (:membership_id, :user_id, :receipt_code, :amount_cents, :issued_at, :secure_hash)'
        );
        $receiptStmt->execute([
            ':membership_id' => $membershipId,
            ':user_id' => $userId,
            ':receipt_code' => $receiptCode,
            ':amount_cents' => 0,
            ':issued_at' => $issuedAt,
            ':secure_hash' => $secureHash,
        ]);

        $_SESSION['user_id'] = $userId;
        $pdo->commit();

        setFlash('success', 'Sócio ativado com sucesso (teste grátis). Código de sócio: ' . $code . '. Comprovante gerado: ' . $receiptCode);
        redirect('socio.php');
    } catch (Throwable $e) {
        $pdo->rollBack();
        setFlash('error', 'Não foi possível ativar agora. Tente novamente.');
        redirect('comprar_socio.php');
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Atlética Barbarus • Ativar Sócio</title>
  <style>
    body{font-family:ui-sans-serif,system-ui;margin:0;background:#f6f7f6;color:#0b0e0c}
    .wrap{max-width:920px;margin:28px auto;padding:0 16px}
    .grid{display:grid;grid-template-columns:1.2fr .8fr;gap:14px}
    .card{background:#fff;border:1px solid rgba(11,14,12,.1);border-radius:16px;padding:16px}
    .badge{display:inline-block;background:#e8f9ee;color:#0f2e22;border:1px solid #9fd9b1;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700}
    h1{margin:10px 0 6px;font-size:28px}
    p{margin:0;color:rgba(11,14,12,.7);line-height:1.65}
    label{display:block;margin-top:10px;font-size:13px;color:rgba(11,14,12,.75)}
    input,select{width:100%;margin-top:4px;padding:10px;border-radius:10px;border:1px solid rgba(11,14,12,.2)}
    .price{margin-top:12px;font-size:30px;font-weight:900;color:#0f2e22}
    .free{font-size:12px;color:#0f7d38;font-weight:700;text-transform:uppercase;letter-spacing:.08em}
    button,.btn{margin-top:12px;width:100%;padding:12px;border-radius:10px;border:0;background:#0f2e22;color:#fff;font-weight:800;cursor:pointer;text-align:center;display:inline-block}
    .btnAlt{background:#ff6a00}
    .toplink{display:inline-block;margin-bottom:10px;color:#0f2e22;font-weight:700}
    .msg{margin-bottom:10px;padding:10px;border-radius:10px;font-size:13px}
    .ok{background:#e8f9ee;border:1px solid #9fd9b1}
    .err{background:#fdecec;border:1px solid #f2b4b4}
    @media (max-width: 780px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <a class="toplink" href="socio.php">← Voltar para Sócio</a>

    <?php if ($flash): ?>
      <div class="msg <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>

    <div class="grid">
      <form class="card" method="post">
        <input type="hidden" name="action" value="activate" />
        <span class="badge">Ativação de sócio</span>
        <h1>Ativar Sócio Barbarus</h1>
        <p>Preencha seus dados para ativar seu sócio e liberar o painel.</p>
        <div class="price">R$ 0,00 / teste</div>
        <div class="free">Teste grátis temporário</div>

        <label>Nome completo
          <input type="text" name="name" value="<?= htmlspecialchars((string) ($user['name'] ?? '')) ?>" required />
        </label>
        <label>Email
          <input type="email" name="email" value="<?= htmlspecialchars((string) ($user['email'] ?? '')) ?>" required />
        </label>
        <label>Telefone / WhatsApp
          <input type="text" name="phone" value="<?= htmlspecialchars((string) ($user['phone'] ?? '')) ?>" minlength="10" placeholder="(77) 99999-9999" required />
        </label>
        <label>CPF
          <input type="text" name="cpf" value="<?= htmlspecialchars((string) ($user['cpf'] ?? '')) ?>" pattern="[0-9]{11}" maxlength="11" placeholder="Somente 11 números" required />
        </label>
        <label>Vínculo
          <select name="affiliation_type" required>
            <option value="">Selecione</option>
            <option value="aluno" <?= (($user['affiliation_type'] ?? '') === 'aluno') ? 'selected' : '' ?>>Aluno(a)</option>
            <option value="egresso" <?= (($user['affiliation_type'] ?? '') === 'egresso') ? 'selected' : '' ?>>Egresso(a)</option>
            <option value="externo" <?= (($user['affiliation_type'] ?? '') === 'externo') ? 'selected' : '' ?>>Externo(a)</option>
          </select>
        </label>
        <label>Curso (obrigatório para aluno)
          <input type="text" name="course_name" value="<?= htmlspecialchars((string) ($user['course_name'] ?? '')) ?>" placeholder="Ex.: BICT" />
        </label>

        <button type="submit">Ativar agora</button>
      </form>

      <div class="card">
        <h2 style="margin:0 0 8px;font-size:18px;text-transform:uppercase">Já é sócio?</h2>
        <p>Se você já tem código de sócio (6 caracteres), entre direto no painel.</p>
        <a class="btn btnAlt" href="codigo_login.php">Entrar com código de sócio</a>

        <h2 style="margin:16px 0 8px;font-size:18px;text-transform:uppercase">Como funciona</h2>
        <p>1. Ative gratuitamente para teste.<br>2. Receba seu código de sócio.<br>3. Use o código para login sempre que quiser.</p>
      </div>
    </div>
  </div>
</body>
</html>
