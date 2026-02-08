<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

function storeUploadedProductImage(array $file): ?string
{
    if (!isset($file['error']) || (int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload da imagem.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Arquivo de imagem inválido.');
    }

    $mime = mime_content_type($tmp) ?: '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Formato de imagem não suportado. Use JPG, PNG, WEBP ou GIF.');
    }

    $dir = __DIR__ . '/uploads/products';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $filename = 'prod_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = $dir . '/' . $filename;

    if (!move_uploaded_file($tmp, $dest)) {
        throw new RuntimeException('Não foi possível salvar a imagem enviada.');
    }

    return 'uploads/products/' . $filename;
}

function storeUploadedProductImages(array $files): array
{
    if (!isset($files['name'])) {
        return [];
    }

    $stored = [];
    if (is_array($files['name'])) {
        $count = count($files['name']);
        for ($i = 0; $i < $count; $i++) {
            $single = [
                'name' => $files['name'][$i] ?? '',
                'type' => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$i] ?? 0,
            ];
            $path = storeUploadedProductImage($single);
            if ($path !== null) {
                $stored[] = $path;
            }
        }
        return $stored;
    }

    $single = storeUploadedProductImage($files);
    if ($single !== null) {
        $stored[] = $single;
    }
    return $stored;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'login_manager') {
        $code = trim((string) ($_POST['access_code'] ?? ''));
        if (hash_equals(normalizeAccessCode(MANAGER_ACCESS_CODE), normalizeAccessCode($code))) {
            $_SESSION['manager_auth'] = true;
            setFlash('success', 'Acesso de gestão liberado.');
        } else {
            setFlash('error', 'Código de gestão inválido.');
        }
        redirect('gestao_loja.php');
    }

    if ($action === 'logout_manager') {
        unset($_SESSION['manager_auth']);
        setFlash('success', 'Sessão de gestão encerrada.');
        redirect('gestao_loja.php');
    }

    if (!managerLogged()) {
        setFlash('error', 'Acesso não autorizado.');
        redirect('gestao_loja.php');
    }

    $pdo = db();

    if ($action === 'add_product') {
        $sector = trim((string) ($_POST['sector_name'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $price = (float) ($_POST['price'] ?? 0);
        $promoText = trim((string) ($_POST['flash_promo_text'] ?? ''));
        $promoPriceRaw = trim((string) ($_POST['flash_promo_price'] ?? ''));
        $promoPrice = $promoPriceRaw === '' ? null : (float) $promoPriceRaw;
        $discountPercent = (int) ($_POST['discount_percent'] ?? 0);

        if ($sector === '' || $name === '' || $description === '' || $price <= 0) {
            setFlash('error', 'Preencha setor, nome, descrição e preço válido.');
            redirect('gestao_loja.php');
        }
        try {
            $image = storeUploadedProductImage($_FILES['image_file'] ?? []);
        } catch (Throwable $e) {
            setFlash('error', $e->getMessage());
            redirect('gestao_loja.php');
        }
        if (!$image) {
            setFlash('error', 'Selecione uma imagem para o produto.');
            redirect('gestao_loja.php');
        }
        try {
            $galleryImages = storeUploadedProductImages($_FILES['gallery_files'] ?? []);
        } catch (Throwable $e) {
            setFlash('error', $e->getMessage());
            redirect('gestao_loja.php');
        }
        $galleryText = $galleryImages ? implode("\n", $galleryImages) : null;

        $stmt = $pdo->prepare(
            'INSERT INTO store_products
            (sector_name, name, description, price_cents, image_url, video_url, gallery_images_text, gallery_videos_text, flash_promo_text, flash_promo_price_cents, discount_percent, is_active, created_at, updated_at)
            VALUES (:sector, :name, :description, :price, :image, NULL, :gallery_images_text, NULL, :promo_text, :promo_price, :discount_percent, 1, :created, :updated)'
        );
        $stmt->execute([
            ':sector' => $sector,
            ':name' => $name,
            ':description' => $description,
            ':price' => (int) round($price * 100),
            ':image' => $image,
            ':gallery_images_text' => $galleryText,
            ':promo_text' => $promoText !== '' ? $promoText : null,
            ':promo_price' => ($promoPrice !== null && $promoPrice > 0) ? (int) round($promoPrice * 100) : null,
            ':discount_percent' => max(0, min(90, $discountPercent)),
            ':created' => nowIso(),
            ':updated' => nowIso(),
        ]);

        setFlash('success', 'Produto adicionado com sucesso.');
        redirect('gestao_loja.php');
    }

    if ($action === 'update_product') {
        $id = (int) ($_POST['id'] ?? 0);
        $sector = trim((string) ($_POST['sector_name'] ?? ''));
        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $price = (float) ($_POST['price'] ?? 0);
        $existingImage = trim((string) ($_POST['existing_image_url'] ?? ''));
        $promoText = trim((string) ($_POST['flash_promo_text'] ?? ''));
        $promoPriceRaw = trim((string) ($_POST['flash_promo_price'] ?? ''));
        $promoPrice = $promoPriceRaw === '' ? null : (float) $promoPriceRaw;
        $discountPercent = (int) ($_POST['discount_percent'] ?? 0);
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $replaceGallery = isset($_POST['replace_gallery']);

        if ($id <= 0 || $sector === '' || $name === '' || $description === '' || $price <= 0) {
            setFlash('error', 'Dados inválidos para atualizar produto.');
            redirect('gestao_loja.php');
        }
        try {
            $uploaded = storeUploadedProductImage($_FILES['image_file'] ?? []);
        } catch (Throwable $e) {
            setFlash('error', $e->getMessage());
            redirect('gestao_loja.php');
        }
        $image = $uploaded ?: ($existingImage !== '' ? $existingImage : null);
        try {
            $uploadedGallery = storeUploadedProductImages($_FILES['gallery_files'] ?? []);
        } catch (Throwable $e) {
            setFlash('error', $e->getMessage());
            redirect('gestao_loja.php');
        }

        $currentStmt = $pdo->prepare('SELECT gallery_images_text FROM store_products WHERE id = :id LIMIT 1');
        $currentStmt->execute([':id' => $id]);
        $currentProduct = $currentStmt->fetch();
        $existingGallery = splitMediaLines((string) ($currentProduct['gallery_images_text'] ?? ''));
        $galleryImages = $replaceGallery
            ? $uploadedGallery
            : array_values(array_unique(array_merge($existingGallery, $uploadedGallery)));
        $galleryText = $galleryImages ? implode("\n", $galleryImages) : null;

        $stmt = $pdo->prepare(
            'UPDATE store_products
             SET sector_name = :sector,
                 name = :name,
                 description = :description,
                 price_cents = :price,
                 image_url = :image,
                 video_url = NULL,
                 gallery_images_text = :gallery_images_text,
                 gallery_videos_text = NULL,
                 flash_promo_text = :promo_text,
                 flash_promo_price_cents = :promo_price,
                 discount_percent = :discount_percent,
                 is_active = :is_active,
                 updated_at = :updated
             WHERE id = :id'
        );
        $stmt->execute([
            ':sector' => $sector,
            ':name' => $name,
            ':description' => $description,
            ':price' => (int) round($price * 100),
            ':image' => $image,
            ':gallery_images_text' => $galleryText,
            ':promo_text' => $promoText !== '' ? $promoText : null,
            ':promo_price' => ($promoPrice !== null && $promoPrice > 0) ? (int) round($promoPrice * 100) : null,
            ':discount_percent' => max(0, min(90, $discountPercent)),
            ':is_active' => $isActive,
            ':updated' => nowIso(),
            ':id' => $id,
        ]);

        setFlash('success', 'Produto atualizado.');
        redirect('gestao_loja.php');
    }
}

$flash = getFlash();
$products = [];
$sales = [];

if (managerLogged()) {
    $products = db()->query('SELECT * FROM store_products ORDER BY updated_at DESC, id DESC')->fetchAll();

    $sales = db()->query(
        'SELECT o.*, r.receipt_code,
                GROUP_CONCAT(i.product_name || " x" || i.quantity, ", ") AS items_summary
         FROM store_orders o
         LEFT JOIN store_order_items i ON i.order_id = o.id
         LEFT JOIN store_receipts r ON r.order_id = o.id
         GROUP BY o.id
         ORDER BY o.created_at DESC'
    )->fetchAll();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Gestão da Loja • Barbarus</title>
  <style>
    body{font-family:ui-sans-serif,system-ui;margin:0;background:#f6f7f6;color:#0b0e0c}
    .wrap{max-width:1280px;margin:26px auto;padding:0 16px}
    .card{background:#fff;border:1px solid rgba(11,14,12,.1);border-radius:16px;padding:16px}
    h1{margin:0 0 8px;font-size:24px;text-transform:uppercase;letter-spacing:.05em}
    h2{margin:0 0 8px;font-size:16px;text-transform:uppercase;letter-spacing:.05em}
    .msg{margin-bottom:10px;padding:10px;border-radius:10px;font-size:13px}
    .ok{background:#e8f9ee;border:1px solid #9fd9b1}
    .err{background:#fdecec;border:1px solid #f2b4b4}
    .toplink{display:inline-block;margin-bottom:10px;color:#0f2e22;font-weight:700}
    .head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:10px}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    label{display:block;margin-top:8px;font-size:12px;color:rgba(11,14,12,.75)}
    input,textarea{width:100%;margin-top:4px;padding:9px;border-radius:10px;border:1px solid rgba(11,14,12,.2);font:inherit}
    textarea{min-height:70px;resize:vertical}
    .btn{margin-top:10px;padding:9px 12px;border-radius:10px;border:0;background:#0f2e22;color:#fff;font-weight:800;cursor:pointer}
    .btnAlt{background:#ff6a00}
    .tableWrap{overflow:auto;border:1px solid rgba(11,14,12,.12);border-radius:12px}
    table{width:100%;border-collapse:collapse;min-width:1080px}
    th,td{padding:10px;border-bottom:1px solid rgba(11,14,12,.08);text-align:left;vertical-align:top;font-size:12px}
    th{background:rgba(15,46,34,.05);font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(11,14,12,.62)}
    tr:last-child td{border-bottom:0}
    .section{margin-top:14px}
    .small{font-size:11px;color:rgba(11,14,12,.62)}
    @media (max-width: 980px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <div class="wrap">
    <a class="toplink" href="index.html">← Voltar para o site</a>

    <?php if ($flash): ?>
      <div class="msg <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>

    <?php if (!managerLogged()): ?>
      <form class="card" method="post">
        <h1>Painel da Loja</h1>
        <p>Digite o código de gestão para administrar produtos, promoções e vendas.</p>
        <input type="hidden" name="action" value="login_manager" />
        <label>Código de acesso
          <input type="password" name="access_code" required />
        </label>
        <button class="btn" type="submit">Entrar</button>
      </form>
    <?php else: ?>
      <div class="card">
        <div class="head">
          <div>
            <h1>Gestão da Loja</h1>
            <p>Cadastre e atualize produtos, promoções relâmpago, imagem principal + múltiplas fotos e acompanhe vendas.</p>
          </div>
          <form method="post">
            <input type="hidden" name="action" value="logout_manager" />
            <button class="btn btnAlt" type="submit">Sair da gestão</button>
          </form>
        </div>

        <div class="section">
          <h2>Novo produto</h2>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_product" />
            <div class="grid">
              <label>Setor
                <input type="text" name="sector_name" placeholder="Ex.: Vestuário" required />
              </label>
              <label>Nome do produto
                <input type="text" name="name" required />
              </label>
              <label>Preço (R$)
                <input type="number" step="0.01" min="0.01" name="price" required />
              </label>
              <label>Imagem do produto (dispositivo)
                <input type="file" name="image_file" accept="image/png,image/jpeg,image/webp,image/gif" required />
              </label>
              <label>Fotos extras (opcional)
                <input type="file" name="gallery_files[]" accept="image/png,image/jpeg,image/webp,image/gif" multiple />
              </label>
              <label>Promo relâmpago (texto)
                <input type="text" name="flash_promo_text" placeholder="Só hoje" />
              </label>
              <label>Preço promo relâmpago (R$)
                <input type="number" step="0.01" min="0.01" name="flash_promo_price" placeholder="Opcional" />
              </label>
              <label>Desconto do produto (%)
                <input type="number" step="1" min="0" max="90" name="discount_percent" value="0" />
              </label>
            </div>
            <label>Descrição
              <textarea name="description" required></textarea>
            </label>
            <button class="btn" type="submit">Adicionar produto</button>
          </form>
        </div>

        <div class="section">
          <h2>Produtos cadastrados</h2>
          <div class="tableWrap">
            <table>
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Setor</th>
                  <th>Nome</th>
                  <th>Preço</th>
                  <th>Descrição</th>
                  <th>Imagem</th>
                  <th>Promo</th>
                  <th>Desconto</th>
                  <th>Ativo</th>
                  <th>Ação</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($products as $p): ?>
                  <tr>
                    <form method="post" enctype="multipart/form-data">
                      <input type="hidden" name="action" value="update_product" />
                      <input type="hidden" name="id" value="<?= (int) $p['id'] ?>" />
                      <input type="hidden" name="existing_image_url" value="<?= htmlspecialchars((string) ($p['image_url'] ?? '')) ?>" />
                      <td>#<?= (int) $p['id'] ?></td>
                      <td><input type="text" name="sector_name" value="<?= htmlspecialchars((string) $p['sector_name']) ?>" required /></td>
                      <td><input type="text" name="name" value="<?= htmlspecialchars((string) $p['name']) ?>" required /></td>
                      <td><input type="number" step="0.01" min="0.01" name="price" value="<?= htmlspecialchars(number_format(((int) $p['price_cents']) / 100, 2, '.', '')) ?>" required /></td>
                      <td><textarea name="description" required><?= htmlspecialchars((string) $p['description']) ?></textarea></td>
                      <td>
                        <?php if (!empty($p['image_url'])): ?>
                          <div class="small">Atual:</div>
                          <img src="<?= htmlspecialchars((string) $p['image_url']) ?>" alt="" style="width:80px;height:60px;object-fit:cover;border:1px solid rgba(11,14,12,.12);border-radius:8px" />
                        <?php endif; ?>
                        <input type="file" name="image_file" accept="image/png,image/jpeg,image/webp,image/gif" />
                        <div class="small" style="margin-top:8px">Fotos extras:</div>
                        <?php foreach (array_slice(splitMediaLines((string) ($p['gallery_images_text'] ?? '')), 0, 4) as $g): ?>
                          <img src="<?= htmlspecialchars($g) ?>" alt="" style="width:56px;height:42px;object-fit:cover;border:1px solid rgba(11,14,12,.12);border-radius:6px;margin:2px 2px 0 0" />
                        <?php endforeach; ?>
                        <input type="file" name="gallery_files[]" accept="image/png,image/jpeg,image/webp,image/gif" multiple />
                        <label class="small"><input type="checkbox" name="replace_gallery" /> substituir fotos extras atuais</label>
                      </td>
                      <td>
                        <input type="text" name="flash_promo_text" value="<?= htmlspecialchars((string) ($p['flash_promo_text'] ?? '')) ?>" placeholder="Texto" />
                        <input type="number" step="0.01" min="0.01" name="flash_promo_price" value="<?= $p['flash_promo_price_cents'] !== null ? htmlspecialchars(number_format(((int) $p['flash_promo_price_cents']) / 100, 2, '.', '')) : '' ?>" placeholder="Preço" />
                      </td>
                      <td><input type="number" step="1" min="0" max="90" name="discount_percent" value="<?= (int) ($p['discount_percent'] ?? 0) ?>" /></td>
                      <td>
                        <label class="small"><input type="checkbox" name="is_active" <?= ((int) $p['is_active'] === 1) ? 'checked' : '' ?> /> ativo</label>
                      </td>
                      <td><button class="btn" type="submit">Salvar</button></td>
                    </form>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$products): ?>
                  <tr><td colspan="10">Nenhum produto cadastrado.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="section">
          <h2>Setor de vendas</h2>
          <div class="tableWrap">
            <table>
              <thead>
                <tr>
                  <th>Data</th>
                  <th>Comprador</th>
                  <th>Contato</th>
                  <th>CPF</th>
                  <th>Itens</th>
                  <th>Total</th>
                  <th>Status</th>
                  <th>Comprovante</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($sales as $s): ?>
                  <?php
                    $rc = (string) ($s['receipt_code'] ?? '');
                    $link = $rc !== '' ? ('receipt_venda.php?rc=' . urlencode($rc) . '&sig=' . urlencode(storeReceiptSignature($rc))) : '#';
                  ?>
                  <tr>
                    <td><?= htmlspecialchars(formatDateTimeBr((string) $s['created_at'])) ?></td>
                    <td><strong><?= htmlspecialchars((string) $s['buyer_name']) ?></strong></td>
                    <td><?= htmlspecialchars((string) $s['buyer_email']) ?><br><span class="small"><?= htmlspecialchars((string) $s['buyer_phone']) ?></span></td>
                    <td><?= htmlspecialchars((string) $s['buyer_cpf']) ?></td>
                    <td><?= htmlspecialchars((string) ($s['items_summary'] ?? '-')) ?></td>
                    <td><?= htmlspecialchars(moneyFromCents((int) $s['total_cents'])) ?></td>
                    <td><?= htmlspecialchars((string) $s['status']) ?></td>
                    <td>
                      <?php if ($link !== '#'): ?>
                        <a class="btn" style="margin:0;padding:7px 10px" target="_blank" href="<?= htmlspecialchars($link) ?>">Abrir</a>
                      <?php else: ?>
                        <span class="small">Sem comprovante</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
                <?php if (!$sales): ?>
                  <tr><td colspan="8">Nenhuma venda registrada ainda.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
