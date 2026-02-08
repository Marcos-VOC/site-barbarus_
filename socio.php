<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = currentUser();
$membership = null;
$daysLeft = 0;
$receiptLink = null;

if ($user !== null) {
    $membership = activeMembership((int) $user['id']);
    if ($membership !== null) {
        $daysLeft = membershipDaysLeft((string) $membership['end_date']);
        $stmt = db()->prepare(
            'SELECT receipt_code
             FROM receipts
             WHERE membership_id = :mid
             ORDER BY issued_at DESC
             LIMIT 1'
        );
        $stmt->execute([':mid' => (int) $membership['id']]);
        $receipt = $stmt->fetch();
        if ($receipt && !empty($receipt['receipt_code'])) {
            $code = (string) $receipt['receipt_code'];
            $receiptLink = 'receipt.php?rc=' . urlencode($code) . '&sig=' . urlencode(receiptSignature($code));
        }
    }
}

$flash = getFlash();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Atlética Barbarus • Sócio Torcedor</title>
  <meta name="description" content="Seja sócio da Atlética Barbarus. Acesse seu painel, cupons e informações de renovação." />
  <style>
    :root{--bg:#f6f7f6;--ink:#0b0e0c;--muted:rgba(11,14,12,.68);--line:rgba(11,14,12,.10);--green:#1ed760;--greenDeep:#0f2e22;--orange:#ff6a00;--r:16px;--r2:20px;--shadow:0 18px 60px rgba(0,0,0,.1);--shadow2:0 12px 36px rgba(0,0,0,.1);--max:1180px}
    *{box-sizing:border-box}
    body{margin:0;background:radial-gradient(900px 520px at 12% -5%, rgba(30,215,96,.14), transparent 60%),radial-gradient(900px 520px at 92% 10%, rgba(255,106,0,.12), transparent 62%),linear-gradient(180deg,#fff 0%,var(--bg) 60%,#f3f4f3 100%);font-family:ui-sans-serif,system-ui;color:var(--ink)}
    a{color:inherit;text-decoration:none}
    .container{max-width:var(--max);margin:0 auto;padding:0 18px}
    .topbar{position:sticky;top:0;z-index:50;background:rgba(246,247,246,.72);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
    .nav{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:14px 0}
    .brand{display:flex;align-items:center;gap:12px;min-width:220px}
    .logoWrap{width:64px;height:64px;display:grid;place-items:center}
    .logoWrap img{width:100%;height:100%;object-fit:contain}
    .brandText h1{margin:0;font-size:16px;letter-spacing:.22em;text-transform:uppercase}
    .brandText p{margin:3px 0 0;color:rgba(11,14,12,.52);font-size:12px}
    .links{display:flex;gap:10px;flex-wrap:wrap}
    .links a{font-size:14px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(11,14,12,.68);padding:8px 10px;border-radius:12px}
    .links a:hover{background:rgba(255,255,255,.65)}
    .btn{display:inline-flex;align-items:center;gap:10px;padding:10px 12px;border-radius:14px;border:1px solid rgba(11,14,12,.12);background:rgba(255,255,255,.75);font-weight:750;cursor:pointer}
    .btnPrimary{border:0;color:#fff;background:linear-gradient(135deg,rgba(15,46,34,1),rgba(15,46,34,.86));box-shadow:0 18px 52px rgba(15,46,34,.18)}
    .btnAccent{border:0;color:#fff;background:linear-gradient(135deg,rgba(255,106,0,1),rgba(255,166,84,.95));box-shadow:0 18px 52px rgba(255,106,0,.18)}
    .hero{padding:24px 0 12px}
    .card{border-radius:var(--r2);border:1px solid var(--line);background:rgba(255,255,255,.84);box-shadow:var(--shadow2);padding:16px}
    h2{margin:0;font-size:28px;text-transform:uppercase;letter-spacing:-.5px}
    p{color:var(--muted);line-height:1.7}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .benefits{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:12px}
    .benefit{border:1px solid var(--line);border-radius:16px;padding:12px;background:rgba(255,255,255,.86)}
    .benefit h4{margin:0 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:.06em}
    .msg{margin-bottom:10px;padding:10px;border-radius:10px;font-size:13px}
    .ok{background:#e8f9ee;border:1px solid #9fd9b1}
    .err{background:#fdecec;border:1px solid #f2b4b4}
    .panelTop{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
    .days{border:1px solid rgba(15,46,34,.18);background:rgba(30,215,96,.14);color:var(--greenDeep);border-radius:14px;padding:10px 12px;min-width:220px}
    .days strong{display:block;font-size:28px;line-height:1}
    .eventsGrid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:12px}
    .eventCard{border:1px solid var(--line);border-radius:16px;padding:12px;background:rgba(255,255,255,.9)}
    .eventTag{display:inline-flex;padding:4px 8px;border-radius:999px;border:1px solid rgba(15,46,34,.18);background:rgba(30,215,96,.14);color:var(--greenDeep);font-size:10px;font-weight:800;letter-spacing:.08em;text-transform:uppercase}
    .eventCard h4{margin:10px 0 6px;font-size:14px;text-transform:uppercase;letter-spacing:.04em}
    .eventMeta{font-size:12px;color:var(--muted2);margin:0}
    .eventDesc{margin:8px 0 0;color:var(--muted);font-size:13px;line-height:1.55}
    .landingHero{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .heroTag{display:inline-flex;align-items:center;padding:6px 10px;border-radius:999px;border:1px solid rgba(11,14,12,.12);background:rgba(255,255,255,.8);font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:var(--muted)}
    .heroIllus{border-radius:16px;border:1px solid rgba(11,14,12,.12);background:radial-gradient(180px 120px at 20% 20%, rgba(30,215,96,.18), transparent 60%),radial-gradient(180px 120px at 80% 80%, rgba(255,106,0,.18), transparent 60%),rgba(255,255,255,.9);height:290px;display:flex;align-items:center;justify-content:center;overflow:hidden}
    .heroIllus img{max-width:100%;max-height:100%;object-fit:contain}
    .heroActions{display:flex;gap:10px;flex-wrap:wrap;margin-top:12px}
    .benefitCards{display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-top:12px}
    .benefitCard{border:1px solid var(--line);border-radius:16px;padding:12px;background:rgba(255,255,255,.9)}
    .benefitCard img{width:100%;height:120px;object-fit:contain;border:1px dashed rgba(11,14,12,.16);border-radius:12px;background:rgba(255,255,255,.95)}
    .benefitCard h4{margin:10px 0 6px;font-size:13px;text-transform:uppercase;letter-spacing:.06em}
    .planCard{margin-top:14px}
    .priceStrong{font-size:30px;font-weight:900;color:var(--greenDeep);line-height:1}
    .payMethod{margin-top:10px;border-radius:12px;border:1px dashed rgba(11,14,12,.16);background:linear-gradient(135deg, rgba(30,215,96,.09), rgba(255,106,0,.09));padding:10px;font-weight:700;color:var(--greenDeep)}
    .planList{margin:10px 0 0;padding-left:18px;color:var(--muted);line-height:1.7}
    .activateBtn{
      margin-top:12px;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      border-radius:999px;
      padding:10px 14px;
      font-weight:900;
      letter-spacing:.04em;
      text-transform:uppercase;
      font-size:12px;
      background: linear-gradient(135deg, rgba(15,46,34,1), rgba(30,215,96,.95));
      color: var(--orange);
      border:1px solid rgba(11,14,12,.12);
      box-shadow: 0 10px 28px rgba(15,46,34,.22);
    }
    @media (max-width: 980px){.links{display:none}.grid{grid-template-columns:1fr}.eventsGrid{grid-template-columns:1fr 1fr}.benefits{grid-template-columns:1fr 1fr}}
    @media (max-width: 980px){.landingHero{grid-template-columns:1fr}.benefitCards{grid-template-columns:1fr 1fr}}
    @media (max-width: 600px){.eventsGrid,.benefits,.benefitCards{grid-template-columns:1fr}}
  </style>
</head>
<body>
  <header class="topbar">
    <div class="container">
      <div class="nav">
        <a class="brand" href="index.html">
          <div class="logoWrap"><img alt="Logo Barbarus" src="img/logo.png" onerror="this.style.display='none'" /></div>
          <div class="brandText">
            <h1>ATLÉTICA BARBARUS</h1>
            <p>UFOB • Força. União. Coragem.</p>
          </div>
        </a>
        <nav class="links">
          <a href="index.html">Início</a>
          <a href="loja.php">Loja</a>
          <a href="historia.html">História</a>
          <a href="diretoria.html">Diretoria</a>
        </nav>
        <div>
          <?php if ($user): ?>
            <a class="btn" href="logout.php">Sair</a>
          <?php else: ?>
            <a class="btn" href="login.php">Login</a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </header>

  <main>
    <div class="container">
      <section class="hero">
        <?php if ($flash): ?>
          <div class="msg <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <?php if ($user && $membership): ?>
          <div class="card">
            <div class="panelTop">
              <div>
                <h2>Painel do sócio</h2>
                <p>Olá, <strong><?= htmlspecialchars((string) $user['name']) ?></strong>. Aqui estão suas oportunidades e dados de renovação.</p>
                <p><strong>Código de sócio:</strong> <?= htmlspecialchars((string) ($membership['member_code'] ?? '---')) ?></p>
                <?php if ($receiptLink): ?>
                  <p><a class="btn btnPrimary" href="<?= htmlspecialchars($receiptLink) ?>" target="_blank">Baixar comprovante</a></p>
                <?php endif; ?>
              </div>
              <div class="days">
                <strong><?= $daysLeft ?> dias</strong>
                <span>até a renovação (vence em <?= htmlspecialchars(formatDateBr((string) $membership['end_date'])) ?>)</span>
              </div>
            </div>

            <div class="eventsGrid">
              <article class="eventCard">
                <span class="eventTag">Oportunidade</span>
                <h4>Treino aberto de vôlei</h4>
                <p class="eventMeta">Quarta • 19h • Ginásio da UFOB</p>
                <p class="eventDesc">Atividade para sócios com integração de calouros e formação de time para campeonato interno.</p>
              </article>
              <article class="eventCard">
                <span class="eventTag">Evento</span>
                <h4>Esquenta Barbarus</h4>
                <p class="eventMeta">Sexta • 21h • Área de eventos</p>
                <p class="eventDesc">Encontro exclusivo para sócios ativos com lote antecipado e entrada facilitada.</p>
              </article>
              <article class="eventCard">
                <span class="eventTag">Inscrição</span>
                <h4>Torneio de truco</h4>
                <p class="eventMeta">Inscrições até domingo</p>
                <p class="eventDesc">Monte dupla, garanta vaga no torneio e represente a Barbarus na próxima disputa.</p>
              </article>
            </div>
          </div>
        <?php else: ?>
          <div class="card">
            <div class="landingHero">
              <div>
                <span class="heroTag">Sócio Torcedor</span>
                <h2>O clã que fortalece a atlética por dentro e por fora.</h2>
                <p>O Sócio Torcedor Barbarus é o jeito oficial de apoiar a atlética, ter voz nos projetos e viver experiências exclusivas com a gente.</p>
                <div class="heroActions">
                  <?php if ($user): ?>
                    <a class="btn btnAccent" href="comprar_socio.php">Ativar sócio (grátis)</a>
                  <?php else: ?>
                    <a class="btn btnAccent" href="comprar_socio.php">Ativar sócio (grátis)</a>
                    <a class="btn btnPrimary" href="codigo_login.php">Já sou sócio (código sócio)</a>
                  <?php endif; ?>
                </div>
              </div>
              <div class="heroIllus">
                <img src="img/torcedor.png" alt="Sócio Torcedor Barbarus" onerror="this.style.display='none'" />
              </div>
            </div>

            <div class="benefitCards">
              <article class="benefitCard">
                <img src="img/des.png" alt="Promoções" onerror="this.style.display='none'" />
                <h4>Promoções</h4>
                <p>Descontos exclusivos na loja oficial e prioridade em campanhas promocionais.</p>
              </article>
              <article class="benefitCard">
                <img src="img/participar.png" alt="Participar da Atlética" onerror="this.style.display='none'" />
                <h4>Participar da Atlética</h4>
                <p>Participa das ações e decisões do clã, ajudando a construir eventos e projetos.</p>
              </article>
              <article class="benefitCard">
                <img src="img/ajudar.png" alt="Apoiar a Atlética" onerror="this.style.display='none'" />
                <h4>Apoiar a Atlética</h4>
                <p>Sua contribuição fortalece treinos, materiais, campeonatos e a estrutura do dia a dia.</p>
              </article>
            </div>

            <div class="card planCard" style="box-shadow:none">
              <h3 style="margin:0 0 8px;letter-spacing:.06em;text-transform:uppercase">Plano único</h3>
              <p>Clã Barbarus • Sócio Torcedor</p>
              <div class="priceStrong">R$ 15,00 <span style="font-size:13px;color:var(--muted);font-weight:700">anuais</span></div>
              <p style="margin-top:10px">Pagamento anual em parcela única.</p>
              <div class="payMethod">PIX • transferência rápida e confirmação no mesmo dia</div>
              <ul class="planList">
                <li>Descontos na loja oficial.</li>
                <li>Participação nos projetos da atlética.</li>
                <li>Apoio direto a treinos e campeonatos.</li>
              </ul>
              <a class="activateBtn" href="codigo_login.php">Já é sócio? Ative</a>
            </div>
          </div>
        <?php endif; ?>
      </section>
    </div>
  </main>
</body>
</html>
