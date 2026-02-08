<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$receiptCode = strtoupper(trim((string) ($_GET['rc'] ?? '')));
$sig = (string) ($_GET['sig'] ?? '');

if ($receiptCode === '' || $sig === '' || !hash_equals(receiptSignature($receiptCode), $sig)) {
    http_response_code(403);
    echo 'Comprovante inválido.';
    exit;
}

$stmt = db()->prepare(
    'SELECT r.*, u.name, u.email, u.cpf, u.affiliation_type, u.course_name, m.member_code, m.start_date, m.end_date
     FROM receipts r
     INNER JOIN users u ON u.id = r.user_id
     INNER JOIN memberships m ON m.id = r.membership_id
     WHERE r.receipt_code = :code
     LIMIT 1'
);
$stmt->execute([':code' => $receiptCode]);
$receipt = $stmt->fetch();

if (!$receipt) {
    http_response_code(404);
    echo 'Comprovante não encontrado.';
    exit;
}

$expectedHash = hash('sha256', $receipt['user_id'] . '|' . $receipt['membership_id'] . '|' . $receipt['issued_at'] . '|' . $receipt['receipt_code'] . '|' . RECEIPT_SECRET);
if (!hash_equals($expectedHash, (string) $receipt['secure_hash'])) {
    http_response_code(403);
    echo 'Comprovante com integridade inválida.';
    exit;
}

$current = currentUser();
$allowed = managerLogged() || ($current && (int) $current['id'] === (int) $receipt['user_id']);
if (!$allowed) {
    http_response_code(403);
    echo 'Você não tem permissão para este comprovante.';
    exit;
}

function moneyFromCents(int $cents): string {
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

$affMap = [
    'aluno' => 'Aluno(a)',
    'egresso' => 'Egresso(a)',
    'externo' => 'Externo(a)',
];
$affLabel = $affMap[$receipt['affiliation_type']] ?? 'Não informado';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Comprovante <?= htmlspecialchars((string) $receipt['receipt_code']) ?></title>
  <style>
    body{font-family:ui-sans-serif,system-ui;margin:0;background:#f6f7f6;color:#0b0e0c}
    .wrap{max-width:780px;margin:28px auto;padding:0 16px}
    .paper{background:#fff;border:2px solid #0f2e22;border-radius:16px;padding:18px;position:relative;overflow:hidden}
    .wm{position:absolute;inset:auto -30px 18px auto;transform:rotate(-12deg);font-size:44px;font-weight:900;color:rgba(15,46,34,.08);letter-spacing:.08em;text-transform:uppercase}
    .head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start;flex-wrap:wrap;border-bottom:1px dashed rgba(11,14,12,.2);padding-bottom:10px}
    h1{margin:0;font-size:22px;text-transform:uppercase;letter-spacing:.05em}
    .meta{font-size:12px;color:rgba(11,14,12,.65)}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}
    .field{border:1px solid rgba(11,14,12,.1);border-radius:10px;padding:10px;background:#fafafa}
    .field strong{display:block;font-size:11px;letter-spacing:.07em;text-transform:uppercase;color:rgba(11,14,12,.6)}
    .field span{display:block;margin-top:4px;font-size:14px}
    .security{margin-top:12px;border:1px dashed rgba(11,14,12,.25);border-radius:10px;padding:10px;background:#fcfffc}
    .security code{display:block;word-break:break-all;font-size:12px;color:#0f2e22}
    .actions{margin-top:12px;display:flex;gap:10px;flex-wrap:wrap}
    .btn{display:inline-block;padding:9px 12px;border-radius:10px;border:1px solid rgba(11,14,12,.2);background:#fff;font-weight:700}
    @media print{.actions{display:none} body{background:#fff} .wrap{margin:0;max-width:none;padding:0} .paper{border-radius:0;border:1px solid #000}}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="paper">
      <div class="wm">Barbarus</div>
      <div class="head">
        <div>
          <h1>Comprovante de Sócio</h1>
          <div class="meta">Atlética Barbarus • UFOB</div>
        </div>
        <div class="meta">
          Código: <strong><?= htmlspecialchars((string) $receipt['receipt_code']) ?></strong><br>
          Emissão: <?= htmlspecialchars(formatDateTimeBr((string) $receipt['issued_at'])) ?>
        </div>
      </div>

      <div class="grid">
        <div class="field"><strong>Nome</strong><span><?= htmlspecialchars((string) $receipt['name']) ?></span></div>
        <div class="field"><strong>Email</strong><span><?= htmlspecialchars((string) $receipt['email']) ?></span></div>
        <div class="field"><strong>CPF</strong><span><?= htmlspecialchars((string) $receipt['cpf']) ?></span></div>
        <div class="field"><strong>Código de sócio</strong><span><?= htmlspecialchars((string) $receipt['member_code']) ?></span></div>
        <div class="field"><strong>Vínculo</strong><span><?= htmlspecialchars($affLabel) ?></span></div>
        <div class="field"><strong>Curso</strong><span><?= htmlspecialchars((string) ($receipt['course_name'] ?: 'Não se aplica')) ?></span></div>
        <div class="field"><strong>Compra em</strong><span><?= htmlspecialchars(formatDateTimeBr((string) $receipt['issued_at'])) ?></span></div>
        <div class="field"><strong>Valor</strong><span><?= moneyFromCents((int) $receipt['amount_cents']) ?> (teste)</span></div>
        <div class="field"><strong>Vigência</strong><span><?= htmlspecialchars(formatDateBr((string) $receipt['start_date'])) ?> até <?= htmlspecialchars(formatDateBr((string) $receipt['end_date'])) ?></span></div>
      </div>

      <div class="security">
        <strong>Assinatura de autenticidade</strong>
        <code><?= htmlspecialchars((string) $receipt['secure_hash']) ?></code>
      </div>

      <div class="actions">
        <a class="btn" href="#" onclick="window.print();return false;">Imprimir / Salvar PDF</a>
        <a class="btn" href="socio.php">Voltar</a>
      </div>
    </div>
  </div>
</body>
</html>
