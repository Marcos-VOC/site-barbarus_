<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = currentUser();
if ($user === null) {
    $next = urlencode('compras.php');
    redirect('login.php?next=' . $next);
}

$email = mb_strtolower(trim((string) ($user['email'] ?? '')));
$cpf = normalizeCpf((string) ($user['cpf'] ?? ''));

$stmt = db()->prepare(
    'SELECT
        o.id,
        o.created_at,
        o.total_cents,
        r.receipt_code,
        GROUP_CONCAT(oi.product_name || " (" || oi.quantity || "x)", ", ") AS product_list
     FROM store_orders o
     INNER JOIN store_order_items oi ON oi.order_id = o.id
     LEFT JOIN store_receipts r ON r.order_id = o.id
     WHERE o.buyer_email = :email OR o.buyer_cpf = :cpf
     GROUP BY o.id, o.created_at, o.total_cents, r.receipt_code
     ORDER BY o.created_at DESC, o.id DESC'
);
$stmt->execute([
    ':email' => $email,
    ':cpf' => $cpf,
]);
$orders = $stmt->fetchAll();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Compras • Loja Barbarus</title>
  <style>
    body{font-family:ui-sans-serif,system-ui;margin:0;background:#f6f7f6;color:#0b0e0c}
    .wrap{max-width:1080px;margin:28px auto;padding:0 16px}
    .card{background:#fff;border:1px solid rgba(11,14,12,.1);border-radius:16px;padding:16px}
    .topbar{display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:10px}
    h1{margin:0;font-size:24px}
    .toplink{display:inline-block;color:#0f2e22;font-weight:700;text-decoration:none}
    .tableWrap{overflow:auto;border:1px solid rgba(11,14,12,.12);border-radius:12px}
    table{width:100%;border-collapse:collapse;min-width:760px}
    th,td{padding:10px;border-bottom:1px solid rgba(11,14,12,.08);text-align:left;font-size:13px;vertical-align:top}
    th{background:rgba(15,46,34,.05);font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(11,14,12,.62)}
    .btn{display:inline-block;padding:8px 11px;border-radius:10px;border:1px solid rgba(11,14,12,.2);background:#fff;font-weight:800;text-decoration:none;color:#0b0e0c}
    .muted{color:rgba(11,14,12,.65)}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <a class="toplink" href="loja.php">← Voltar para loja</a>
      <a class="btn" href="carrinho.php">&#128722; Carrinho</a>
    </div>

    <div class="card">
      <h1>Minhas compras</h1>
      <p class="muted">Histórico simples com produto, data da compra e comprovante.</p>

      <?php if (!$orders): ?>
        <p>Nenhuma compra encontrada para sua conta.</p>
      <?php else: ?>
        <div class="tableWrap">
          <table>
            <thead>
              <tr>
                <th>Produto(s)</th>
                <th>Data da compra</th>
                <th>Total</th>
                <th>Comprovante</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $o): ?>
                <?php
                  $code = trim((string) ($o['receipt_code'] ?? ''));
                  $receiptLink = $code !== '' ? ('receipt_venda.php?rc=' . urlencode($code) . '&sig=' . urlencode(storeReceiptSignature($code))) : '';
                ?>
                <tr>
                  <td><?= htmlspecialchars((string) ($o['product_list'] ?? '-')) ?></td>
                  <td><?= htmlspecialchars(formatDateTimeBr((string) ($o['created_at'] ?? ''))) ?></td>
                  <td><?= htmlspecialchars(moneyFromCents((int) ($o['total_cents'] ?? 0))) ?></td>
                  <td>
                    <?php if ($receiptLink !== ''): ?>
                      <a class="btn" href="<?= htmlspecialchars($receiptLink) ?>" target="_blank">Abrir comprovante</a>
                    <?php else: ?>
                      <span class="muted">Indisponível</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
