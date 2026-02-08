<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = currentUser();
$membership = $user ? activeMembership((int) $user['id']) : null;
$hasActiveMember = $membership !== null;
$cart = $_SESSION['cart'] ?? [];
$cartCount = 0;
foreach ($cart as $q) { $cartCount += (int) $q; }

$products = db()->query('SELECT * FROM store_products WHERE is_active = 1 ORDER BY updated_at DESC, id DESC')->fetchAll();
$flash = getFlash();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Atlética Barbarus • UFOB — Loja</title>
  <meta name="description" content="Loja oficial da Atlética Barbarus (UFOB). Produtos, kits e promoções." />
  <meta name="theme-color" content="#0F2E22" />
  <style>
    :root{--bg:#F6F7F6;--ink:#0B0E0C;--muted:rgba(11,14,12,.68);--muted2:rgba(11,14,12,.52);--line:rgba(11,14,12,.10);--shadow:0 18px 60px rgba(0,0,0,.10);--shadow2:0 12px 36px rgba(0,0,0,.10);--green:#1ED760;--greenDeep:#0F2E22;--orange:#FF6A00;--r2:20px;--max:1180px}
    *{box-sizing:border-box} body{margin:0;color:var(--ink);font-family:ui-sans-serif,system-ui;background:radial-gradient(900px 520px at 12% -5%, rgba(30,215,96,.14), transparent 60%),radial-gradient(900px 520px at 92% 10%, rgba(255,106,0,.12), transparent 62%),linear-gradient(180deg,#FFFFFF 0%, var(--bg) 60%, #F3F4F3 100%)}
    a{text-decoration:none;color:inherit}
    .container{max-width:var(--max);margin:0 auto;padding:0 18px}
    .topbar{position:sticky;top:0;z-index:50;background:rgba(246,247,246,.72);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
    .nav{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 0}
    .brand{display:flex;align-items:center;gap:12px}.logoWrap{width:64px;height:64px;display:grid;place-items:center}.logoWrap img{width:100%;height:100%;object-fit:contain}
    .brandText h1{margin:0;font-size:16px;letter-spacing:.22em;text-transform:uppercase;line-height:1.1}.brandText p{margin:3px 0 0;color:var(--muted2);font-size:12px}
    .links{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.links a{font-size:14px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--muted);padding:8px 10px;border-radius:12px}.links a:hover{background:rgba(255,255,255,.65)}
    .btn{display:inline-flex;align-items:center;gap:10px;padding:10px 12px;border-radius:14px;border:1px solid rgba(11,14,12,.12);background:rgba(255,255,255,.75);font-weight:750;cursor:pointer}
    .btnAccent{border:0;color:#fff;background:linear-gradient(135deg, rgba(255,106,0,1), rgba(255,166,84,.95));box-shadow:0 18px 52px rgba(255,106,0,.18)}
    .hero{padding:24px 0 8px}.heroCard{border-radius:var(--r2);border:1px solid var(--line);background:rgba(255,255,255,.82);box-shadow:var(--shadow);padding:18px;display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap}
    .heroCard h2{margin:0;font-size:24px}.heroCard p{margin:6px 0 0;color:var(--muted)}
    .memberBanner{margin-top:12px;border-radius:12px;border:1px solid rgba(15,46,34,.16);background:linear-gradient(135deg, rgba(30,215,96,.14), rgba(255,106,0,.10));color:var(--greenDeep);padding:10px 12px;font-size:13px;font-weight:700}
    .msg{margin-top:12px;padding:10px;border-radius:10px;font-size:13px}.ok{background:#e8f9ee;border:1px solid #9fd9b1}.err{background:#fdecec;border:1px solid #f2b4b4}
    .grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:14px 0 22px}
    .product{border-radius:var(--r2);border:1px solid var(--line);background:rgba(255,255,255,.82);box-shadow:var(--shadow2);overflow:hidden;display:flex;flex-direction:column;min-height:360px;cursor:pointer;transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease}
    .product:hover{transform:translateY(-6px) scale(1.015);box-shadow:0 20px 46px rgba(15,46,34,.18);border-color:rgba(15,46,34,.28)}
    .product:hover .media img{transform:scale(1.06)}
    .media{height:190px;background:linear-gradient(135deg, rgba(15,46,34,.10), rgba(255,106,0,.12));display:grid;place-items:center;position:relative;overflow:hidden}
    .media img{width:100%;height:100%;object-fit:cover;display:block;transition:transform .25s ease}
    .fallback{font-size:12px;letter-spacing:.06em;text-transform:uppercase;color:var(--muted2);font-weight:800}
    .sector{position:absolute;top:10px;left:10px;background:rgba(255,255,255,.84);border:1px solid rgba(11,14,12,.12);border-radius:999px;padding:4px 8px;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .promo{position:absolute;top:10px;right:10px;background:#ff6a00;color:#fff;border-radius:999px;padding:4px 8px;font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .body{padding:14px;display:flex;flex-direction:column;gap:8px;flex:1}.body h3{margin:0;font-size:14px;letter-spacing:.06em;text-transform:uppercase}.body p{margin:0;color:var(--muted);font-size:13px;line-height:1.6}
    .price{margin-top:auto;display:flex;flex-direction:column;gap:3px}.priceOriginal{color:var(--muted2);text-decoration:line-through;font-size:12px;font-weight:700}.priceNow{color:var(--greenDeep);font-size:20px;font-weight:900;line-height:1.1}.brotherMsg{font-size:12px;color:#0f7d38;font-weight:700}
    .actions{padding:0 14px 14px;display:flex;gap:8px;flex-wrap:wrap}.actions .btn{justify-content:center}
    footer{padding:22px 0 34px;border-top:1px solid rgba(11,14,12,.10);margin-top:10px;color:var(--muted2);font-size:12px}.footerBottom{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center}
    @media (max-width:980px){.links{display:none}.grid{grid-template-columns:repeat(2,1fr)}}
    @media (max-width:520px){.grid{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="container">
      <div class="nav">
        <a class="brand" href="index.html" aria-label="Página inicial">
          <div class="logoWrap"><img alt="Logo Barbarus" src="img/logo.png" onerror="this.style.display='none'" /></div>
          <div class="brandText"><h1>ATLÉTICA BARBARUS</h1><p>UFOB • Força. União. Coragem.</p></div>
        </a>
        <nav class="links">
          <a href="socio.php">Clã Barbarus (Sócio)</a>
          <a href="loja.php">Nossa loja</a>
          <a href="historia.html">História e legado</a>
          <a href="diretoria.html">Diretoria</a>
          <a href="contato.html">Fale conosco</a>
        </nav>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          <a class="btn" href="carrinho.php">&#128722; Carrinho (<?= $cartCount ?>)</a>
          <a class="btn" href="compras.php">Compras</a>
          <a class="btn" href="gestao_loja.php">Gestão loja</a>
          <a class="btn btnAccent" href="contato.html">Falar no WhatsApp</a>
        </div>
      </div>
    </div>
  </header>

  <main>
    <div class="container">
      <section class="hero">
        <div class="heroCard">
          <div>
            <h2>Loja Oficial Barbarus</h2>
            <p>Produtos do clã, kits e promoções relâmpago atualizados pela gestão.</p>
            <?php if ($hasActiveMember): ?>
              <div class="memberBanner">Desconto de sócio já aplicado automaticamente nas compras.</div>
            <?php else: ?>
              <div class="memberBanner">Não é sócio? Compre ou ative caso for, não perca descontos. Não vacila.</div>
            <?php endif; ?>
          </div>
          <a class="btn btnAccent" href="socio.php">Virar sócio</a>
        </div>
        <?php if ($flash): ?>
          <div class="msg <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>
      </section>

      <section>
        <div class="grid">
          <?php foreach ($products as $p): ?>
            <?php
              $meta = productPriceMeta($p, $hasActiveMember);
              $rawBase = (int) $meta['list_price_cents'];
              $hasPromo = !empty($meta['promo_price_cents']);
              $baseForDisplay = $hasPromo ? (int) $meta['promo_price_cents'] : $rawBase;
              $hasCampaignDiscount = ((int) $meta['discount_percent']) > 0;
              $afterCampaign = (int) $meta['after_campaign_cents'];
              $final = (int) $meta['final_price_cents'];
            ?>
            <article class="product" data-href="produto.php?pid=<?= (int) $p['id'] ?>" tabindex="0" role="link" aria-label="Abrir detalhes do produto <?= htmlspecialchars((string) $p['name']) ?>">
              <div class="media">
                <span class="sector"><?= htmlspecialchars((string) $p['sector_name']) ?></span>
                <?php if (!empty($p['flash_promo_text'])): ?><span class="promo"><?= htmlspecialchars((string) $p['flash_promo_text']) ?></span><?php endif; ?>
                <?php if (!empty($p['image_url'])): ?>
                  <img src="<?= htmlspecialchars((string) $p['image_url']) ?>" alt="<?= htmlspecialchars((string) $p['name']) ?>" onerror="this.style.display='none';this.nextElementSibling.style.display='block'" />
                  <div class="fallback" style="display:none">Sem imagem</div>
                <?php else: ?>
                  <div class="fallback">Produto ilustrativo</div>
                <?php endif; ?>
              </div>
              <div class="body">
                <h3><?= htmlspecialchars((string) $p['name']) ?></h3>
                <p><?= htmlspecialchars((string) $p['description']) ?></p>
                <div class="price">
                  <?php if ($hasCampaignDiscount): ?>
                    <span class="priceOriginal"><?= htmlspecialchars(moneyFromCents($baseForDisplay)) ?></span>
                    <span class="priceNow"><?= htmlspecialchars(moneyFromCents($afterCampaign)) ?></span>
                    <span class="brotherMsg">Desconto do produto <?= (int) $meta['discount_percent'] ?>% aplicado</span>
                  <?php endif; ?>
                  <?php if ($hasActiveMember): ?>
                    <span class="priceOriginal"><?= htmlspecialchars(moneyFromCents($hasCampaignDiscount ? $afterCampaign : $baseForDisplay)) ?></span>
                    <span class="priceNow"><?= htmlspecialchars(moneyFromCents($final)) ?></span>
                    <span class="brotherMsg">Faço mais barato porque tu é meu brother</span>
                  <?php else: ?>
                    <?php if (!$hasCampaignDiscount): ?>
                      <?php if ($hasPromo): ?><span class="priceOriginal"><?= htmlspecialchars(moneyFromCents($rawBase)) ?></span><?php endif; ?>
                      <span class="priceNow"><?= htmlspecialchars(moneyFromCents($baseForDisplay)) ?></span>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
              <div class="actions">
                <a class="btn" href="produto.php?pid=<?= (int) $p['id'] ?>">Detalhes</a>
                <a class="btn" href="carrinho.php?action=add&pid=<?= (int) $p['id'] ?>">&#128722; Carrinho</a>
                <a class="btn btnAccent" href="comprar_produto.php?pid=<?= (int) $p['id'] ?>">Comprar</a>
              </div>
            </article>
          <?php endforeach; ?>
          <?php if (!$products): ?>
            <div class="card" style="padding:16px;border:1px solid var(--line);border-radius:16px;background:rgba(255,255,255,.85)">Nenhum produto ativo no momento.</div>
          <?php endif; ?>
        </div>
      </section>

      <footer>
        <div class="footerBottom">
          <span>© <span id="year"></span> Atlética Barbarus • UFOB</span>
          <div style="display:flex; gap:14px">
            <a href="https://instagram.com/ufobarbarus" target="_blank">Instagram</a>
            <a href="mailto:aaabicet@gmail.com">Email</a>
            <a href="contato.html">Contato</a>
          </div>
        </div>
      </footer>
    </div>
  </main>

  <script>
    document.getElementById('year').textContent = new Date().getFullYear();
    document.querySelectorAll('.product[data-href]').forEach(function(card){
      card.addEventListener('click', function(e){
        if (e.target.closest('a,button,input,textarea,select,label,form')) return;
        window.location.href = card.getAttribute('data-href');
      });
      card.addEventListener('keydown', function(e){
        if ((e.key === 'Enter' || e.key === ' ') && !e.target.closest('a,button,input,textarea,select,label,form')) {
          e.preventDefault();
          window.location.href = card.getAttribute('data-href');
        }
      });
    });
  </script>
</body>
</html>
