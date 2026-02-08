<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$user = currentUser();
if ($user !== null) {
    redirect('socio.php');
}

$next = isset($_GET['next']) ? (string) $_GET['next'] : 'socio.php';
if (!str_ends_with($next, '.php')) {
    $next = 'socio.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'register') {
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        if ($name === '' || $email === '' || strlen($password) < 6) {
            setFlash('error', 'Preencha nome, email e senha (mínimo 6 caracteres).');
            redirect('login.php?next=' . urlencode($next));
        }

        try {
            $stmt = db()->prepare(
                'INSERT INTO users (name, email, password_hash, created_at)
                 VALUES (:name, :email, :hash, :created)'
            );
            $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':hash' => password_hash($password, PASSWORD_DEFAULT),
                ':created' => nowIso(),
            ]);
            $_SESSION['user_id'] = (int) db()->lastInsertId();
            setFlash('success', 'Conta criada com sucesso.');
            redirect($next);
        } catch (Throwable $e) {
            setFlash('error', 'Não foi possível criar a conta. Email pode já estar em uso.');
            redirect('login.php?next=' . urlencode($next));
        }
    }

    if ($action === 'login') {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');

        $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();

        if ($row && password_verify($password, (string) $row['password_hash'])) {
            $_SESSION['user_id'] = (int) $row['id'];
            setFlash('success', 'Login realizado com sucesso.');
            redirect($next);
        }

        setFlash('error', 'Email ou senha inválidos.');
        redirect('login.php?next=' . urlencode($next));
    }
}

$flash = getFlash();
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Atlética Barbarus • Login</title>
  <style>
    body{font-family:ui-sans-serif,system-ui; margin:0; background:#f6f7f6; color:#0b0e0c}
    .wrap{max-width:900px; margin:28px auto; padding:0 16px}
    .grid{display:grid; grid-template-columns:1fr 1fr; gap:14px}
    .card{background:#fff; border:1px solid rgba(11,14,12,.1); border-radius:16px; padding:16px}
    h1{margin:0 0 10px; font-size:24px}
    h2{margin:0 0 8px; font-size:16px; text-transform:uppercase; letter-spacing:.06em}
    label{display:block; margin-top:10px; font-size:13px; color:rgba(11,14,12,.75)}
    input{width:100%; margin-top:4px; padding:10px; border-radius:10px; border:1px solid rgba(11,14,12,.2)}
    button{margin-top:12px; width:100%; padding:10px 12px; border-radius:10px; border:0; background:#0f2e22; color:#fff; font-weight:700; cursor:pointer}
    .alt{background:#ff6a00}
    .msg{margin-bottom:10px; padding:10px; border-radius:10px; font-size:13px}
    .ok{background:#e8f9ee; border:1px solid #9fd9b1}
    .err{background:#fdecec; border:1px solid #f2b4b4}
    .toplink{display:inline-block; margin-bottom:10px; color:#0f2e22; font-weight:700}
    @media (max-width: 760px){ .grid{grid-template-columns:1fr} }
  </style>
</head>
<body>
  <div class="wrap">
    <a class="toplink" href="socio.php">← Voltar para Sócio</a>
    <h1>Acesse o Sócio Barbarus</h1>
    <?php if ($flash): ?>
      <div class="msg <?= $flash['type'] === 'success' ? 'ok' : 'err' ?>"><?= htmlspecialchars($flash['message']) ?></div>
    <?php endif; ?>
    <div class="grid">
      <form class="card" method="post">
        <h2>Entrar</h2>
        <input type="hidden" name="action" value="login" />
        <label>Email
          <input type="email" name="email" required />
        </label>
        <label>Senha
          <input type="password" name="password" required />
        </label>
        <button type="submit">Entrar</button>
      </form>

      <form class="card" method="post">
        <h2>Criar conta</h2>
        <input type="hidden" name="action" value="register" />
        <label>Nome completo
          <input type="text" name="name" required />
        </label>
        <label>Email
          <input type="email" name="email" required />
        </label>
        <label>Senha (mínimo 6 caracteres)
          <input type="password" name="password" minlength="6" required />
        </label>
        <button class="alt" type="submit">Criar conta</button>
      </form>
    </div>
  </div>
</body>
</html>
