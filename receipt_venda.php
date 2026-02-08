<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$receiptCode = strtoupper(trim((string) ($_GET['rc'] ?? '')));
$sig = (string) ($_GET['sig'] ?? '');

if ($receiptCode === '' || $sig === '' || !hash_equals(storeReceiptSignature($receiptCode), $sig)) {
    http_response_code(403);
    echo 'Comprovante de venda inválido.';
    exit;
}

$stmt = db()->prepare(
    'SELECT r.*, o.buyer_name, o.buyer_email, o.buyer_phone, o.buyer_cpf, o.total_cents, o.created_at, o.status
     FROM store_receipts r
     INNER JOIN store_orders o ON o.id = r.order_id
     WHERE r.receipt_code = :code
     LIMIT 1'
);
$stmt->execute([':code' => $receiptCode]);
$data = $stmt->fetch();

if (!$data) {
    http_response_code(404);
    echo 'Comprovante não encontrado.';
    exit;
}

$expectedHash = hash('sha256', $data['order_id'] . '|' . $data['receipt_code'] . '|' . $data['issued_at'] . '|' . RECEIPT_SECRET);
if (!hash_equals($expectedHash, (string) $data['secure_hash'])) {
    http_response_code(403);
    echo 'Comprovante inválido por integridade.';
    exit;
}

// Link assinado + hash de integridade já validam o acesso do comprovante.

$itemsStmt = db()->prepare(
    'SELECT product_name, quantity, unit_price_cents
     FROM store_order_items
     WHERE order_id = :oid
     ORDER BY id ASC'
);
$itemsStmt->execute([':oid' => (int) $data['order_id']]);
$items = $itemsStmt->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Comprovante de venda <?= htmlspecialchars((string) $data['receipt_code']) ?></title>
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
          <h1>Comprovante de venda</h1>
          <div class="meta">Atlética Barbarus • Loja oficial</div>
        </div>
        <div class="meta">
          Código: <strong><?= htmlspecialchars((string) $data['receipt_code']) ?></strong><br>
          Emissão: <?= htmlspecialchars(formatDateTimeBr((string) $data['issued_at'])) ?>
        </div>
      </div>

      <div class="grid">
        <div class="field"><strong>Comprador</strong><span><?= htmlspecialchars((string) $data['buyer_name']) ?></span></div>
        <div class="field"><strong>Email</strong><span><?= htmlspecialchars((string) $data['buyer_email']) ?></span></div>
        <div class="field"><strong>Telefone</strong><span><?= htmlspecialchars((string) $data['buyer_phone']) ?></span></div>
        <div class="field"><strong>CPF</strong><span><?= htmlspecialchars((string) $data['buyer_cpf']) ?></span></div>
        <div class="field"><strong>Total</strong><span><?= htmlspecialchars(moneyFromCents((int) $data['total_cents'])) ?></span></div>
        <div class="field"><strong>Status</strong><span><?= htmlspecialchars((string) $data['status']) ?></span></div>
        <div class="field"><strong>Compra em</strong><span><?= htmlspecialchars(formatDateTimeBr((string) $data['created_at'])) ?></span></div>
      </div>

      <div class="security" style="margin-top:10px">
        <strong>Itens da compra</strong>
        <?php foreach ($items as $it): ?>
          <div style="margin-top:6px;font-size:13px">
            <?= htmlspecialchars((string) $it['product_name']) ?> •
            <?= (int) $it['quantity'] ?>x •
            <?= htmlspecialchars(moneyFromCents((int) $it['unit_price_cents'])) ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="security">
        <strong>Assinatura de autenticidade</strong>
        <code><?= htmlspecialchars((string) $data['secure_hash']) ?></code>
      </div>

      <div class="actions">
        <a class="btn" href="#" onclick="window.print();return false;">Imprimir / Salvar PDF</a>
        <a class="btn" href="compras.php">Minhas compras</a>
        <a class="btn" href="loja.php">Voltar para loja</a>
      </div>
    </div>
  </div>
</body>
</html>
