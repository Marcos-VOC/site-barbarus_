<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$productId = (int) ($_GET['pid'] ?? $_POST['pid'] ?? 0);
if ($productId <= 0) {
    setFlash('error', 'Produto inválido.');
    redirect('loja.php');
}

$stmt = db()->prepare('SELECT * FROM store_products WHERE id = :id AND is_active = 1 LIMIT 1');
$stmt->execute([':id' => $productId]);
$product = $stmt->fetch();
if (!$product) {
    setFlash('error', 'Produto não encontrado ou inativo.');
    redirect('loja.php');
}

$user = currentUser();
$flash = getFlash();

$basePriceCents = (int) $product['price_cents'];
if (!empty($product['flash_promo_price_cents']) && (int) $product['flash_promo_price_cents'] > 0) {
    $basePriceCents = (int) $product['flash_promo_price_cents'];
}

$membership = $user ? activeMembership((int) $user['id']) : null;
$hasMember = $membership !== null;
$finalPriceCents = $hasMember ? (int) round($basePriceCents * 0.85) : $basePriceCents;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buyerName = trim((string) ($_POST['buyer_name'] ?? ''));
    $buyerEmail = mb_strtolower(trim((string) ($_POST['buyer_email'] ?? '')));
    $buyerPhone = trim((string) ($_POST['buyer_phone'] ?? ''));
    $buyerCpf = normalizeCpf((string) ($_POST['buyer_cpf'] ?? ''));

    if (
        $buyerName === '' ||
        !filter_var($buyerEmail, FILTER_VALIDATE_EMAIL) ||
        strlen(preg_replace('/\D+/', '', $buyerPhone) ?? '') < 10 ||
        !isValidCpf($buyerCpf)
    ) {
        setFlash('error', 'Revise os dados: nome, email válido, telefone e CPF válido.');
        redirect('comprar_produto.php?pid=' . $productId);
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $orderStmt = $pdo->prepare(
            'INSERT INTO store_orders
            (buyer_name, buyer_email, buyer_phone, buyer_cpf, total_cents, created_at, status)
            VALUES (:name, :email, :phone, :cpf, :total, :created, :status)'
        );
        $orderStmt->execute([
            ':name' => $buyerName,
            ':email' => $buyerEmail,
            ':phone' => $buyerPhone,
            ':cpf' => $buyerCpf,
            ':total' => $finalPriceCents,
            ':created' => nowIso(),
            ':status' => 'pago_teste',
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO store_order_items
            (order_id, product_id, product_name, unit_price_cents, quantity)
            VALUES (:order_id, :product_id, :product_name, :unit_price_cents, :quantity)'
        );
        $itemStmt->execute([
            ':order_id' => $orderId,
            ':product_id' => $productId,
            ':product_name' => (string) $product['name'],
            ':unit_price_cents' => $finalPriceCents,
            ':quantity' => 1,
        ]);

        $receiptCode = makeStoreReceiptCode();
        $issuedAt = nowIso();
        $secureHash = hash('sha256', $orderId . '|' . $receiptCode . '|' . $issuedAt . '|' . RECEIPT_SECRET);

        $receiptStmt = $pdo->prepare(
            'INSERT INTO store_receipts (order_id, receipt_code, issued_at, secure_hash)
             VALUES (:order_id, :receipt_code, :issued_at, :secure_hash)'
        );
        $receiptStmt->execute([
            ':order_id' => $orderId,
            ':receipt_code' => $receiptCode,
            ':issued_at' => $issuedAt,
            ':secure_hash' => $secureHash,
        ]);

        $pdo->commit();

        $link = 'receipt_venda.php?rc=' . urlencode($receiptCode) . '&sig=' . urlencode(storeReceiptSignature($receiptCode));
        redirect($link);
    } catch (Throwable $e) {
        $pdo->rollBack();
        setFlash('error', 'Não foi possível concluir a compra agora.');
        redirect('comprar_produto.php?pid=' . $productId);
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Comprar produto • Barbarus</title>
  <style>
    body{font-family:ui-sans-serif,system-ui;margin:0;background:#f6f7f6;color:#0b0e0c}
    .wrap{max-width:920px;margin:28px auto;padding:0 16px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .card{background:#fff;border:1px solid rgba(11,14,12,.1);border-radius:16px;padding:16px}
    h1{margin:0 0 8px;font-size:24px}
    p{margin:0;color:rgba(11,14,12,.7);line-height:1.6}
    label{display:block;margin-top:10px;font-size:13px;color:rgba(11,14,12,.75)}
    input{width:100%;margin-top:4px;padding:10px;border-radius:10px;border:1px solid rgba(11,14,12,.2)}
    .price{margin-top:10px;font-size:30px;font-weight:900;color:#0f2e22}
    .msg{margin-bottom:10px;padding:10px;border-radius:10px;font-size:13px}
    .ok{background:#e8f9ee;border:1px solid #9fd9b1}
    .err{background:#fdecec;border:1px solid #f2b4b4}
    button{margin-top:12px;width:100%;padding:12px;border-radius:10px;border:0;background:#0f2e22;color:#fff;font-weight:800;cursor:pointer}
    .toplink{display:inline-block;margin-bottom:10px;color:#0f2e22;font-weight:700}
    .badge{display:inline-block;padding:5px 8px;border-radius:999px;background:#e8f9ee;border:1px solid #9fd9b1;color:#0f2e22;font-size:11px;font-weight:800;letter-spacing:.07em;text-transform:uppercase}
    @media (max-width: 780px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <a class="toplink" href="loja.php">← Voltar para loja</a>

    <?php if ($flash): ?>
      <div class="msg <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>

    <div class="grid">
      <div class="card">
        <span class="badge">Compra de produto</span>
        <h1><?= htmlspecialchars((string) $product['name']) ?></h1>
        <p><?= htmlspecialchars((string) $product['description']) ?></p>
        <div class="price"><?= htmlspecialchars(moneyFromCents($finalPriceCents)) ?></div>
        <?php if ($hasMember): ?>
          <p style="margin-top:6px;color:#0f7d38;font-weight:700">Desconto de sócio já aplicado.</p>
        <?php endif; ?>
        <?php if (!empty($product['flash_promo_text'])): ?>
          <p style="margin-top:6px;color:#a94d00;font-weight:700">Promo relâmpago: <?= htmlspecialchars((string) $product['flash_promo_text']) ?></p>
        <?php endif; ?>
      </div>

      <form class="card" method="post">
        <input type="hidden" name="pid" value="<?= (int) $product['id'] ?>" />
        <h1 style="font-size:20px">Dados para compra</h1>
        <label>Nome completo
          <input type="text" name="buyer_name" value="<?= htmlspecialchars((string) ($user['name'] ?? '')) ?>" required />
        </label>
        <label>Email
          <input type="email" name="buyer_email" value="<?= htmlspecialchars((string) ($user['email'] ?? '')) ?>" required />
        </label>
        <label>Telefone
          <input type="text" name="buyer_phone" value="<?= htmlspecialchars((string) ($user['phone'] ?? '')) ?>" minlength="10" required />
        </label>
        <label>CPF
          <input type="text" name="buyer_cpf" value="<?= htmlspecialchars((string) ($user['cpf'] ?? '')) ?>" pattern="[0-9]{11}" maxlength="11" required />
        </label>
        <button type="submit">Finalizar compra (teste)</button>
      </form>
    </div>
  </div>
</body>
</html>
