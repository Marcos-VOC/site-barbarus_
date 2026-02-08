<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$pid = (int) ($_GET['pid'] ?? 0);
if ($pid <= 0) {
    setFlash('error', 'Produto inválido.');
    redirect('loja.php');
}

$stmt = db()->prepare('SELECT * FROM store_products WHERE id = :id AND is_active = 1 LIMIT 1');
$stmt->execute([':id' => $pid]);
$product = $stmt->fetch();
if (!$product) {
    setFlash('error', 'Produto não encontrado.');
    redirect('loja.php');
}

$user = currentUser();
$membership = $user ? activeMembership((int) $user['id']) : null;
$hasMember = $membership !== null;
$meta = productPriceMeta($product, $hasMember);

$images = [];
$mainImage = trim((string) ($product['image_url'] ?? ''));
if ($mainImage !== '') {
    $images[] = $mainImage;
}
foreach (splitMediaLines((string) ($product['gallery_images_text'] ?? '')) as $extra) {
    $images[] = $extra;
}
$images = array_values(array_unique($images));
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= htmlspecialchars((string) $product['name']) ?> • Loja Barbarus</title>
  <style>
    :root{--bg:#f6f7f6;--ink:#0b0e0c;--green:#0f2e22;--orange:#ff6a00;--line:rgba(11,14,12,.12)}
    *{box-sizing:border-box}
    body{font-family:ui-sans-serif,system-ui;margin:0;background:
      radial-gradient(900px 420px at 6% -6%, rgba(30,215,96,.17), transparent 62%),
      radial-gradient(900px 420px at 95% 10%, rgba(255,106,0,.14), transparent 62%),
      var(--bg);color:var(--ink)}
    .wrap{max-width:1180px;margin:26px auto;padding:0 16px}
    .toplink{display:inline-block;margin-bottom:10px;color:var(--green);font-weight:800;text-decoration:none}
    .grid{display:grid;grid-template-columns:1.15fr .85fr;gap:14px;align-items:start}
    .card{background:rgba(255,255,255,.9);border:1px solid var(--line);border-radius:18px;padding:16px;box-shadow:0 14px 36px rgba(0,0,0,.08)}
    h1{margin:0 0 8px;font-size:32px;text-transform:uppercase;letter-spacing:.04em;line-height:1.05}
    p{margin:0;color:rgba(11,14,12,.74);line-height:1.65}
    .galleryMain{position:relative;border-radius:14px;overflow:hidden;border:1px solid var(--line);background:#edf1ee;min-height:380px;display:grid;place-items:center}
    .galleryMain img{width:100%;height:100%;object-fit:cover;display:block}
    .noimg{font-weight:700;color:rgba(11,14,12,.55)}
    .navBtn{position:absolute;top:50%;transform:translateY(-50%);border:0;border-radius:999px;width:40px;height:40px;background:rgba(15,46,34,.82);color:#fff;font-size:20px;cursor:pointer}
    .navPrev{left:10px}.navNext{right:10px}
    .thumbs{display:grid;grid-template-columns:repeat(5,1fr);gap:8px;margin-top:10px}
    .thumb{border:2px solid transparent;border-radius:10px;overflow:hidden;background:#fff;padding:0;cursor:pointer;min-height:68px}
    .thumb.active{border-color:var(--orange)}
    .thumb img{width:100%;height:100%;object-fit:cover;display:block}
    .purchaseTop{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;flex-wrap:wrap}
    .tag{display:inline-block;padding:5px 9px;border-radius:999px;background:var(--orange);color:#fff;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .price{margin-top:10px;display:flex;flex-direction:column;gap:4px}
    .priceOriginal{color:rgba(11,14,12,.55);text-decoration:line-through;font-size:13px;font-weight:700}
    .priceNow{color:var(--green);font-size:34px;font-weight:900;line-height:1}
    .payTitle{margin:12px 0 6px;font-size:12px;color:rgba(11,14,12,.66);font-weight:800;letter-spacing:.07em;text-transform:uppercase}
    .payments{display:flex;gap:8px;flex-wrap:wrap}
    .pay{border:1px solid rgba(11,14,12,.15);background:#fff;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700}
    .actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
    .btn{display:inline-flex;align-items:center;justify-content:center;padding:11px 13px;border-radius:12px;border:0;background:var(--green);color:#fff;font-weight:800;text-decoration:none;cursor:pointer}
    .btnAlt{background:var(--orange)}
    .desc{margin-top:14px}
    .desc h2{margin:0 0 8px;font-size:14px;letter-spacing:.09em;text-transform:uppercase}
    @media (max-width:980px){.grid{grid-template-columns:1fr}.galleryMain{min-height:300px}.thumbs{grid-template-columns:repeat(4,1fr)}}
    @media (max-width:560px){.thumbs{grid-template-columns:repeat(3,1fr)}}
  </style>
</head>
<body>
  <div class="wrap">
    <a class="toplink" href="loja.php">← Voltar para loja</a>

    <div class="grid">
      <div class="card">
        <div class="galleryMain">
          <?php if ($images): ?>
            <img id="galleryImage" src="<?= htmlspecialchars((string) $images[0]) ?>" alt="<?= htmlspecialchars((string) $product['name']) ?>" />
            <?php if (count($images) > 1): ?>
              <button class="navBtn navPrev" type="button" aria-label="Foto anterior">&#8249;</button>
              <button class="navBtn navNext" type="button" aria-label="Próxima foto">&#8250;</button>
            <?php endif; ?>
          <?php else: ?>
            <div class="noimg">Sem imagem cadastrada</div>
          <?php endif; ?>
        </div>
        <?php if ($images): ?>
          <div class="thumbs" id="galleryThumbs">
            <?php foreach ($images as $i => $img): ?>
              <button class="thumb <?= $i === 0 ? 'active' : '' ?>" type="button" data-index="<?= (int) $i ?>">
                <img src="<?= htmlspecialchars((string) $img) ?>" alt="Foto <?= (int) ($i + 1) ?>" />
              </button>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <div class="card">
        <div class="purchaseTop">
          <h1><?= htmlspecialchars((string) $product['name']) ?></h1>
          <?php if (!empty($product['flash_promo_text'])): ?>
            <span class="tag"><?= htmlspecialchars((string) $product['flash_promo_text']) ?></span>
          <?php endif; ?>
        </div>

        <div class="price">
          <?php if (!empty($meta['promo_price_cents']) || ((int) $meta['discount_percent']) > 0 || $hasMember): ?>
            <span class="priceOriginal"><?= htmlspecialchars(moneyFromCents((int) $meta['list_price_cents'])) ?></span>
          <?php endif; ?>
          <span class="priceNow"><?= htmlspecialchars(moneyFromCents((int) $meta['final_price_cents'])) ?></span>
          <?php if (((int) $meta['discount_percent']) > 0): ?><span>Desconto do produto <?= (int) $meta['discount_percent'] ?>% aplicado.</span><?php endif; ?>
          <?php if ($hasMember): ?><span>Desconto de sócio já aplicado.</span><?php endif; ?>
        </div>

        <div class="payTitle">Opções de pagamento</div>
        <div class="payments">
          <span class="pay">⚡ PIX</span>
          <span class="pay">💳 Cartão</span>
          <span class="pay">🧾 Boleto</span>
        </div>

        <div class="actions">
          <a class="btn" href="carrinho.php?action=add&pid=<?= (int) $product['id'] ?>">&#128722; Adicionar ao carrinho</a>
          <a class="btn btnAlt" href="comprar_produto.php?pid=<?= (int) $product['id'] ?>">Comprar agora</a>
        </div>

        <div class="desc">
          <h2>Descrição do produto</h2>
          <p><?= htmlspecialchars((string) $product['description']) ?></p>
        </div>
      </div>
    </div>
  </div>
  <?php if ($images): ?>
    <script>
      (function(){
        const images = <?= json_encode(array_values($images), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
        let index = 0;
        const img = document.getElementById('galleryImage');
        const thumbs = Array.from(document.querySelectorAll('#galleryThumbs .thumb'));
        if (!img || !thumbs.length) return;

        function render() {
          img.src = images[index];
          thumbs.forEach((t, i) => t.classList.toggle('active', i === index));
        }

        const prev = document.querySelector('.navPrev');
        const next = document.querySelector('.navNext');
        if (prev) prev.addEventListener('click', function(){ index = (index - 1 + images.length) % images.length; render(); });
        if (next) next.addEventListener('click', function(){ index = (index + 1) % images.length; render(); });
        thumbs.forEach((thumb, i) => thumb.addEventListener('click', function(){ index = i; render(); }));
      })();
    </script>
  <?php endif; ?>
</body>
</html>
