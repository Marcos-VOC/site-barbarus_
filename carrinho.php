<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$action = (string) ($_GET['action'] ?? $_POST['action'] ?? '');
$pid = (int) ($_GET['pid'] ?? $_POST['pid'] ?? 0);

if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($action === 'add' && $pid > 0) {
    $stmt = db()->prepare('SELECT id FROM store_products WHERE id = :id AND is_active = 1 LIMIT 1');
    $stmt->execute([':id' => $pid]);
    if ($stmt->fetch()) {
        $_SESSION['cart'][$pid] = (int) (($_SESSION['cart'][$pid] ?? 0) + 1);
        setFlash('success', 'Produto adicionado ao carrinho.');
    } else {
        setFlash('error', 'Produto inválido para carrinho.');
    }
    redirect('carrinho.php');
}

if ($action === 'remove' && $pid > 0) {
    unset($_SESSION['cart'][$pid]);
    setFlash('success', 'Item removido do carrinho.');
    redirect('carrinho.php');
}

$user = currentUser();
$membership = $user ? activeMembership((int) $user['id']) : null;
$hasMember = $membership !== null;

$flash = getFlash();
$cart = $_SESSION['cart'];
$cart = array_filter($cart, fn($q) => (int) $q > 0);
$_SESSION['cart'] = $cart;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $newQty = $_POST['qty'] ?? [];
    foreach ($newQty as $id => $qty) {
        $id = (int) $id;
        $qty = max(0, min(50, (int) $qty));
        if ($qty === 0) {
            unset($_SESSION['cart'][$id]);
        } else {
            $_SESSION['cart'][$id] = $qty;
        }
    }
    setFlash('success', 'Carrinho atualizado.');
    redirect('carrinho.php');
}

$items = [];
$totalCents = 0;

if ($cart) {
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare('SELECT * FROM store_products WHERE id IN (' . $placeholders . ') AND is_active = 1');
    $stmt->execute($ids);
    $products = $stmt->fetchAll();

    foreach ($products as $p) {
        $id = (int) $p['id'];
        $qty = (int) ($cart[$id] ?? 0);
        if ($qty <= 0) {
            continue;
        }
        $meta = productPriceMeta($p, $hasMember);
        $unit = (int) $meta['final_price_cents'];
        $line = $unit * $qty;
        $totalCents += $line;

        $items[] = [
            'product' => $p,
            'qty' => $qty,
            'unit_cents' => $unit,
            'line_cents' => $line,
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'checkout') {
    if (!$items) {
        setFlash('error', 'Carrinho vazio.');
        redirect('carrinho.php');
    }

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
        redirect('carrinho.php');
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
            ':total' => $totalCents,
            ':created' => nowIso(),
            ':status' => 'pago_teste',
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO store_order_items
            (order_id, product_id, product_name, unit_price_cents, quantity)
            VALUES (:order_id, :product_id, :product_name, :unit_price_cents, :quantity)'
        );

        foreach ($items as $it) {
            $p = $it['product'];
            $itemStmt->execute([
                ':order_id' => $orderId,
                ':product_id' => (int) $p['id'],
                ':product_name' => (string) $p['name'],
                ':unit_price_cents' => (int) $it['unit_cents'],
                ':quantity' => (int) $it['qty'],
            ]);
        }

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
        $_SESSION['cart'] = [];

        redirect('receipt_venda.php?rc=' . urlencode($receiptCode) . '&sig=' . urlencode(storeReceiptSignature($receiptCode)));
    } catch (Throwable $e) {
        $pdo->rollBack();
        setFlash('error', 'Não foi possível fechar a compra agora.');
        redirect('carrinho.php');
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Carrinho • Loja Barbarus</title>
  <style>
    body{font-family:ui-sans-serif,system-ui;margin:0;background:#f6f7f6;color:#0b0e0c}
    .wrap{max-width:1080px;margin:28px auto;padding:0 16px}
    .card{background:#fff;border:1px solid rgba(11,14,12,.1);border-radius:16px;padding:16px}
    .msg{margin-bottom:10px;padding:10px;border-radius:10px;font-size:13px}.ok{background:#e8f9ee;border:1px solid #9fd9b1}.err{background:#fdecec;border:1px solid #f2b4b4}
    h1{margin:0 0 8px;font-size:24px}
    .toplink{display:inline-block;margin-bottom:10px;color:#0f2e22;font-weight:700}
    .tableWrap{overflow:auto;border:1px solid rgba(11,14,12,.12);border-radius:12px}
    table{width:100%;border-collapse:collapse;min-width:720px}
    th,td{padding:10px;border-bottom:1px solid rgba(11,14,12,.08);text-align:left;font-size:13px}
    th{background:rgba(15,46,34,.05);font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(11,14,12,.62)}
    .qty{width:72px;padding:6px;border-radius:8px;border:1px solid rgba(11,14,12,.2)}
    .btn{display:inline-block;padding:8px 11px;border-radius:10px;border:0;background:#0f2e22;color:#fff;font-weight:800;cursor:pointer;text-decoration:none}
    .btnAlt{background:#ff6a00}
    .grid{display:grid;grid-template-columns:1fr 360px;gap:14px;margin-top:14px}
    label{display:block;margin-top:10px;font-size:12px;color:rgba(11,14,12,.75)}
    input{width:100%;margin-top:4px;padding:9px;border-radius:10px;border:1px solid rgba(11,14,12,.2)}
    .total{font-size:24px;font-weight:900;color:#0f2e22}
    @media (max-width: 980px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
      <a class="toplink" href="loja.php">← Continuar comprando</a>
      <a class="btn" href="compras.php">Minhas compras</a>
    </div>
    <?php if ($flash): ?><div class="msg <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif; ?>

    <div class="card">
      <h1>&#128722; Seu carrinho</h1>
      <?php if (!$items): ?>
        <p>Carrinho vazio no momento.</p>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="action" value="update" />
          <div class="tableWrap">
            <table>
              <thead><tr><th>Produto</th><th>Preço unitário</th><th>Qtd</th><th>Total</th><th>Ação</th></tr></thead>
              <tbody>
                <?php foreach ($items as $it): ?>
                  <?php $p = $it['product']; ?>
                  <tr>
                    <td><?= htmlspecialchars((string) $p['name']) ?></td>
                    <td><?= htmlspecialchars(moneyFromCents((int) $it['unit_cents'])) ?></td>
                    <td><input class="qty" type="number" min="0" max="50" name="qty[<?= (int) $p['id'] ?>]" value="<?= (int) $it['qty'] ?>" /></td>
                    <td><?= htmlspecialchars(moneyFromCents((int) $it['line_cents'])) ?></td>
                    <td><a class="btn btnAlt" href="carrinho.php?action=remove&pid=<?= (int) $p['id'] ?>">Remover</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <button class="btn" style="margin-top:10px" type="submit">Atualizar carrinho</button>
        </form>
      <?php endif; ?>
    </div>

    <?php if ($items): ?>
      <div class="grid">
        <div class="card">
          <h1 style="font-size:20px">Resumo</h1>
          <div class="total"><?= htmlspecialchars(moneyFromCents($totalCents)) ?></div>
          <?php if ($hasMember): ?><p style="color:#0f7d38;font-weight:700">Desconto de sócio já aplicado.</p><?php endif; ?>
        </div>
        <form class="card" method="post">
          <input type="hidden" name="action" value="checkout" />
          <h1 style="font-size:20px">Finalizar compra</h1>
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
          <button class="btn" type="submit" style="margin-top:12px;width:100%">Concluir compra (teste)</button>
        </form>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
