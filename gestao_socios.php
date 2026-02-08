<?php
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

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
        redirect('gestao_socios.php');
    }

    if ($action === 'logout_manager') {
        unset($_SESSION['manager_auth']);
        setFlash('success', 'Sessão de gestão encerrada.');
        redirect('gestao_socios.php');
    }
}

$flash = getFlash();
$rows = [];
$totalSocios = 0;
$affCounts = ['aluno' => 0, 'egresso' => 0, 'externo' => 0];
$courseCounts = [];

if (managerLogged()) {
    $stmt = db()->query(
        'SELECT
            u.id as user_id,
            u.name,
            u.email,
            u.phone,
            u.cpf,
            u.affiliation_type,
            u.course_name,
            m.member_code,
            m.start_date,
            m.end_date,
            m.created_at as purchase_at,
            r.receipt_code,
            r.issued_at
         FROM memberships m
         INNER JOIN users u ON u.id = m.user_id
         LEFT JOIN receipts r ON r.membership_id = m.id
         WHERE m.status = "active"
         ORDER BY m.created_at DESC'
    );
    $rows = $stmt->fetchAll();

    $totalSocios = count($rows);
    foreach ($rows as $r) {
        $aff = (string) ($r['affiliation_type'] ?? '');
        if (isset($affCounts[$aff])) {
            $affCounts[$aff]++;
        }
        $course = trim((string) ($r['course_name'] ?? ''));
        if ($course !== '') {
            $courseCounts[$course] = ($courseCounts[$course] ?? 0) + 1;
        }
    }
    arsort($courseCounts);
}

$affMap = [
    'aluno' => 'Aluno(a)',
    'egresso' => 'Egresso(a)',
    'externo' => 'Externo(a)',
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Gestão de Sócios • Barbarus</title>
  <style>
    body{font-family:ui-sans-serif,system-ui;margin:0;background:#f6f7f6;color:#0b0e0c}
    .wrap{max-width:1240px;margin:26px auto;padding:0 16px}
    .card{background:#fff;border:1px solid rgba(11,14,12,.1);border-radius:16px;padding:16px}
    h1{margin:0 0 8px;font-size:26px;text-transform:uppercase;letter-spacing:.05em}
    p{margin:0;color:rgba(11,14,12,.7)}
    .msg{margin-bottom:10px;padding:10px;border-radius:10px;font-size:13px}
    .ok{background:#e8f9ee;border:1px solid #9fd9b1}
    .err{background:#fdecec;border:1px solid #f2b4b4}
    label{display:block;margin-top:10px;font-size:13px;color:rgba(11,14,12,.75)}
    input{width:100%;margin-top:4px;padding:10px;border-radius:10px;border:1px solid rgba(11,14,12,.2)}
    button,.btn{margin-top:12px;padding:10px 12px;border-radius:10px;border:0;background:#0f2e22;color:#fff;font-weight:800;cursor:pointer;text-decoration:none;display:inline-block}
    .btnAlt{background:#ff6a00}
    .head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap;margin-bottom:10px}
    .metrics{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:12px 0}
    .metric{border:1px solid rgba(11,14,12,.1);border-radius:12px;padding:12px;background:#fcfcfc}
    .metric h2{margin:0 0 8px;font-size:14px;letter-spacing:.06em;text-transform:uppercase}
    .big{font-size:30px;font-weight:900;color:#0f2e22;line-height:1}
    .barList{display:grid;gap:8px;margin-top:8px}
    .barItem{display:grid;grid-template-columns:120px 1fr auto;align-items:center;gap:8px;font-size:12px}
    .barTrack{height:10px;border-radius:999px;background:#eef1ef;overflow:hidden}
    .barFill{height:100%;background:linear-gradient(90deg, #1ed760, #0f2e22)}
    .tableWrap{overflow:auto;margin-top:12px;border:1px solid rgba(11,14,12,.12);border-radius:12px}
    table{width:100%;border-collapse:collapse;min-width:1120px}
    th,td{padding:10px 12px;border-bottom:1px solid rgba(11,14,12,.08);text-align:left;font-size:13px;vertical-align:top}
    th{background:rgba(15,46,34,.05);font-size:11px;letter-spacing:.08em;text-transform:uppercase;color:rgba(11,14,12,.62)}
    tr:last-child td{border-bottom:0}
    .small{font-size:12px;color:rgba(11,14,12,.62)}
    .toplink{display:inline-block;margin-bottom:10px;color:#0f2e22;font-weight:700}
    @media (max-width: 900px){.metrics{grid-template-columns:1fr}}
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
        <h1>Painel de Gestão</h1>
        <p>Digite o código de gestão para visualizar a base de sócios.</p>
        <input type="hidden" name="action" value="login_manager" />
        <label>Código de acesso
          <input type="password" name="access_code" required />
        </label>
        <button type="submit">Entrar no painel</button>
      </form>
    <?php else: ?>
      <div class="card">
        <div class="head">
          <div>
            <h1>Sócios cadastrados</h1>
            <p>Tabela de gestão com dados de cadastro, vínculo e comprovante.</p>
          </div>
          <form method="post">
            <input type="hidden" name="action" value="logout_manager" />
            <button class="btnAlt" type="submit">Sair da gestão</button>
          </form>
        </div>

        <div class="metrics">
          <div class="metric">
            <h2>Total de sócios pelo site</h2>
            <div class="big"><?= $totalSocios ?></div>
            <p class="small" style="margin-top:6px">Assinaturas ativas registradas no sistema.</p>
          </div>

          <div class="metric">
            <h2>Distribuição por vínculo</h2>
            <div class="barList">
              <?php foreach ($affCounts as $k => $v): ?>
                <?php $pct = $totalSocios > 0 ? (int) round(($v / $totalSocios) * 100) : 0; ?>
                <div class="barItem">
                  <span><?= htmlspecialchars($affMap[$k] ?? $k) ?></span>
                  <div class="barTrack"><div class="barFill" style="width: <?= $pct ?>%"></div></div>
                  <strong><?= $v ?></strong>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <div class="metric">
          <h2>Quantidade por curso</h2>
          <div class="barList">
            <?php if ($courseCounts): ?>
              <?php
                $maxCourse = max($courseCounts);
                $i = 0;
              ?>
              <?php foreach ($courseCounts as $course => $qty): ?>
                <?php
                  if ($i >= 8) { break; }
                  $pct = $maxCourse > 0 ? (int) round(($qty / $maxCourse) * 100) : 0;
                  $i++;
                ?>
                <div class="barItem">
                  <span><?= htmlspecialchars($course) ?></span>
                  <div class="barTrack"><div class="barFill" style="width: <?= $pct ?>%"></div></div>
                  <strong><?= $qty ?></strong>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <p class="small">Sem cursos informados ainda.</p>
            <?php endif; ?>
          </div>
        </div>

        <div class="tableWrap">
          <table>
            <thead>
              <tr>
                <th>Nome</th>
                <th>CPF</th>
                <th>Email / Telefone</th>
                <th>Vínculo</th>
                <th>Curso</th>
                <th>Código Sócio</th>
                <th>Compra em</th>
                <th>Vigência</th>
                <th>Comprovante</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $row): ?>
                <?php
                  $receiptLink = '#';
                  if (!empty($row['receipt_code'])) {
                      $sig = receiptSignature((string) $row['receipt_code']);
                      $receiptLink = 'receipt.php?rc=' . urlencode((string) $row['receipt_code']) . '&sig=' . urlencode($sig);
                  }
                ?>
                <tr>
                  <td><strong><?= htmlspecialchars((string) $row['name']) ?></strong></td>
                  <td><?= htmlspecialchars((string) $row['cpf']) ?></td>
                  <td>
                    <?= htmlspecialchars((string) $row['email']) ?><br>
                    <span class="small"><?= htmlspecialchars((string) $row['phone']) ?></span>
                  </td>
                  <td><?= htmlspecialchars($affMap[$row['affiliation_type']] ?? 'Não informado') ?></td>
                  <td><?= htmlspecialchars((string) ($row['course_name'] ?: '-')) ?></td>
                  <td><code><?= htmlspecialchars((string) $row['member_code']) ?></code></td>
                  <td><?= htmlspecialchars(formatDateTimeBr((string) $row['purchase_at'])) ?></td>
                  <td><?= htmlspecialchars(formatDateBr((string) $row['start_date'])) ?> até <?= htmlspecialchars(formatDateBr((string) $row['end_date'])) ?></td>
                  <td>
                    <?php if ($receiptLink !== '#'): ?>
                      <a class="btn" style="margin-top:0;padding:7px 10px" target="_blank" href="<?= htmlspecialchars($receiptLink) ?>">Abrir</a>
                    <?php else: ?>
                      <span class="small">Sem comprovante</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$rows): ?>
                <tr><td colspan="9">Nenhum sócio ativo encontrado.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
