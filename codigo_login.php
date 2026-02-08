<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = (string) ($_POST['member_code'] ?? '');
    $result = loginByMemberCode($code);

    if ($result) {
        $_SESSION['user_id'] = (int) $result['id'];
        setFlash('success', 'Login realizado com sucesso pelo código de sócio.');
        redirect('socio.php');
    }

    setFlash('error', 'Código inválido ou assinatura inativa.');
    redirect('codigo_login.php');
}

$flash = getFlash();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Atlética Barbarus • Login por Código</title>
  <style>
    body{font-family:ui-sans-serif,system-ui;margin:0;background:#f6f7f6;color:#0b0e0c}
    .wrap{max-width:560px;margin:34px auto;padding:0 16px}
    .card{background:#fff;border:1px solid rgba(11,14,12,.1);border-radius:16px;padding:16px}
    h1{margin:0 0 8px;font-size:26px}
    p{margin:0;color:rgba(11,14,12,.7)}
    label{display:block;margin-top:12px;font-size:13px;color:rgba(11,14,12,.75)}
    input{width:100%;margin-top:4px;padding:12px;border-radius:12px;border:1px solid rgba(11,14,12,.2);font-size:18px;text-transform:uppercase;letter-spacing:.2em;text-align:center}
    button{margin-top:14px;width:100%;padding:12px;border-radius:10px;border:0;background:#0f2e22;color:#fff;font-weight:800;cursor:pointer}
    .msg{margin-bottom:10px;padding:10px;border-radius:10px;font-size:13px}
    .ok{background:#e8f9ee;border:1px solid #9fd9b1}
    .err{background:#fdecec;border:1px solid #f2b4b4}
    .toplink{display:inline-block;margin-bottom:10px;color:#0f2e22;font-weight:700}
  </style>
</head>
<body>
  <div class="wrap">
    <a class="toplink" href="socio.php">← Voltar para Sócio</a>
    <?php if ($flash): ?>
      <div class="msg <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>
    <form class="card" method="post">
      <h1>Login por código</h1>
      <p>Digite seu código de sócio (6 letras/números).</p>
      <label>Código de sócio
        <input type="text" name="member_code" maxlength="6" minlength="6" required placeholder="ABC123" />
      </label>
      <button type="submit">Entrar no painel</button>
    </form>
  </div>
</body>
</html>
