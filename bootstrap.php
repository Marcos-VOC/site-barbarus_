<?php
declare(strict_types=1);

$localSessionPath = __DIR__ . '/data/sessions';
if (!is_dir($localSessionPath)) {
    @mkdir($localSessionPath, 0777, true);
}
@chmod($localSessionPath, 0777);

if (!is_writable($localSessionPath)) {
    $localSessionPath = sys_get_temp_dir() . '/barbarus_sessions';
    if (!is_dir($localSessionPath)) {
        @mkdir($localSessionPath, 0777, true);
    }
    @chmod($localSessionPath, 0777);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path($localSessionPath);
    session_start();
}

date_default_timezone_set('America/Sao_Paulo');

const DB_PATH = __DIR__ . '/data/site.sqlite';
const RECEIPT_SECRET = 'barbarus-receipt-secret-change-this';
const MANAGER_ACCESS_CODE = 'barbarus-gestao-2026';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (!is_dir(__DIR__ . '/data')) {
        mkdir(__DIR__ . '/data', 0775, true);
    }

    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    initSchema($pdo);
    return $pdo;
}

function initSchema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            phone TEXT,
            cpf TEXT,
            affiliation_type TEXT,
            course_name TEXT,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS memberships (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            status TEXT NOT NULL,
            member_code TEXT,
            start_date TEXT NOT NULL,
            end_date TEXT NOT NULL,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            title TEXT NOT NULL,
            code TEXT NOT NULL,
            discount_percent INTEGER NOT NULL,
            expires_at TEXT NOT NULL,
            is_used INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL,
            FOREIGN KEY(user_id) REFERENCES users(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS receipts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            membership_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            receipt_code TEXT NOT NULL UNIQUE,
            amount_cents INTEGER NOT NULL,
            issued_at TEXT NOT NULL,
            secure_hash TEXT NOT NULL,
            FOREIGN KEY(membership_id) REFERENCES memberships(id),
            FOREIGN KEY(user_id) REFERENCES users(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS store_products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sector_name TEXT NOT NULL,
            name TEXT NOT NULL,
            description TEXT NOT NULL,
            price_cents INTEGER NOT NULL,
            image_url TEXT,
            video_url TEXT,
            gallery_images_text TEXT,
            gallery_videos_text TEXT,
            flash_promo_text TEXT,
            flash_promo_price_cents INTEGER,
            discount_percent INTEGER NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS store_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            buyer_name TEXT NOT NULL,
            buyer_email TEXT NOT NULL,
            buyer_phone TEXT NOT NULL,
            buyer_cpf TEXT NOT NULL,
            total_cents INTEGER NOT NULL,
            created_at TEXT NOT NULL,
            status TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS store_order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            product_name TEXT NOT NULL,
            unit_price_cents INTEGER NOT NULL,
            quantity INTEGER NOT NULL,
            FOREIGN KEY(order_id) REFERENCES store_orders(id),
            FOREIGN KEY(product_id) REFERENCES store_products(id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS store_receipts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            receipt_code TEXT NOT NULL UNIQUE,
            issued_at TEXT NOT NULL,
            secure_hash TEXT NOT NULL,
            FOREIGN KEY(order_id) REFERENCES store_orders(id)
        )'
    );

    ensureColumn($pdo, 'memberships', 'member_code', 'TEXT');
    ensureColumn($pdo, 'users', 'affiliation_type', 'TEXT');
    ensureColumn($pdo, 'users', 'course_name', 'TEXT');
    ensureColumn($pdo, 'store_products', 'video_url', 'TEXT');
    ensureColumn($pdo, 'store_products', 'gallery_images_text', 'TEXT');
    ensureColumn($pdo, 'store_products', 'gallery_videos_text', 'TEXT');
    ensureColumn($pdo, 'store_products', 'flash_promo_text', 'TEXT');
    ensureColumn($pdo, 'store_products', 'flash_promo_price_cents', 'INTEGER');
    ensureColumn($pdo, 'store_products', 'discount_percent', 'INTEGER NOT NULL DEFAULT 0');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_memberships_member_code ON memberships(member_code)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_store_orders_created_at ON store_orders(created_at)');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_store_receipts_code ON store_receipts(receipt_code)');

    seedStoreProducts($pdo);
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void
{
    $stmt = $pdo->query('PRAGMA table_info(' . $table . ')');
    $columns = $stmt ? $stmt->fetchAll() : [];
    foreach ($columns as $col) {
        if (($col['name'] ?? '') === $column) {
            return;
        }
    }
    $pdo->exec('ALTER TABLE ' . $table . ' ADD COLUMN ' . $column . ' ' . $definition);
}

function nowIso(): string
{
    return (new DateTimeImmutable())->format('Y-m-d H:i:s');
}

function todayDate(): string
{
    return (new DateTimeImmutable())->format('Y-m-d');
}

function formatDateBr(?string $isoDate): string
{
    if (!$isoDate) {
        return '-';
    }
    try {
        return (new DateTimeImmutable($isoDate))->format('d/m/Y');
    } catch (Throwable $e) {
        return $isoDate;
    }
}

function formatDateTimeBr(?string $isoDateTime): string
{
    if (!$isoDateTime) {
        return '-';
    }
    try {
        return (new DateTimeImmutable($isoDateTime))->format('d/m/Y H:i');
    } catch (Throwable $e) {
        return $isoDateTime;
    }
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function currentUser(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, name, email, phone, cpf, affiliation_type, course_name FROM users WHERE id = :id');
    $stmt->execute([':id' => (int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    return $user ?: null;
}

function managerLogged(): bool
{
    return !empty($_SESSION['manager_auth']) && $_SESSION['manager_auth'] === true;
}

function normalizeAccessCode(string $value): string
{
    return strtolower(preg_replace('/[^a-z0-9]/i', '', $value) ?? '');
}

function requireManager(): void
{
    if (!managerLogged()) {
        redirect('gestao_socios.php');
    }
}

function requireLogin(): array
{
    $user = currentUser();
    if ($user === null) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? 'socio.php');
        redirect('login.php?next=' . $next);
    }
    return $user;
}

function activeMembership(int $userId): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM memberships
         WHERE user_id = :uid
           AND status = "active"
           AND date(end_date) >= date(:today)
         ORDER BY end_date DESC
         LIMIT 1'
    );
    $stmt->execute([':uid' => $userId, ':today' => todayDate()]);
    $membership = $stmt->fetch();
    return $membership ?: null;
}

function membershipDaysLeft(string $endDate): int
{
    $today = new DateTimeImmutable(todayDate());
    $end = new DateTimeImmutable($endDate);
    if ($end < $today) {
        return 0;
    }
    return (int) $today->diff($end)->days + 1;
}

function normalizeMemberCode(string $code): string
{
    return strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code) ?? '');
}

function normalizeCpf(string $cpf): string
{
    return preg_replace('/\D+/', '', $cpf) ?? '';
}

function isValidCpf(string $cpf): bool
{
    $cpf = normalizeCpf($cpf);
    if (strlen($cpf) !== 11) {
        return false;
    }
    if (preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }
    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += ((int) $cpf[$i]) * (($t + 1) - $i);
        }
        $digit = ((10 * $sum) % 11) % 10;
        if ((int) $cpf[$t] !== $digit) {
            return false;
        }
    }
    return true;
}

function generateMemberCode(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $pdo = db();

    while (true) {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        $stmt = $pdo->prepare('SELECT 1 FROM memberships WHERE member_code = :code LIMIT 1');
        $stmt->execute([':code' => $code]);
        if (!$stmt->fetchColumn()) {
            return $code;
        }
    }
}

function loginByMemberCode(string $rawCode): ?array
{
    $code = normalizeMemberCode($rawCode);
    if (strlen($code) !== 6) {
        return null;
    }

    $stmt = db()->prepare(
        'SELECT u.id, u.name, u.email, m.end_date
         FROM memberships m
         INNER JOIN users u ON u.id = m.user_id
         WHERE m.member_code = :code
           AND m.status = "active"
           AND date(m.end_date) >= date(:today)
         ORDER BY m.end_date DESC
         LIMIT 1'
    );
    $stmt->execute([
        ':code' => $code,
        ':today' => todayDate(),
    ]);

    return $stmt->fetch() ?: null;
}

function issueDefaultCoupons(int $userId, string $membershipEndDate): void
{
    $coupons = [
        ['Desconto loja oficial', 10],
        ['Evento parceiro', 15],
        ['Produto selecionado', 20],
    ];

    $stmt = db()->prepare(
        'INSERT INTO coupons (user_id, title, code, discount_percent, expires_at, created_at)
         VALUES (:uid, :title, :code, :discount, :expires, :created)'
    );

    foreach ($coupons as [$title, $discount]) {
        $code = 'BARB-' . strtoupper(bin2hex(random_bytes(3)));
        $stmt->execute([
            ':uid' => $userId,
            ':title' => $title,
            ':code' => $code,
            ':discount' => $discount,
            ':expires' => $membershipEndDate,
            ':created' => nowIso(),
        ]);
    }
}

function makeReceiptCode(): string
{
    return 'RB-' . strtoupper(bin2hex(random_bytes(4)));
}

function receiptSignature(string $receiptCode): string
{
    return hash_hmac('sha256', $receiptCode, RECEIPT_SECRET);
}

function makeStoreReceiptCode(): string
{
    return 'LV-' . strtoupper(bin2hex(random_bytes(4)));
}

function storeReceiptSignature(string $receiptCode): string
{
    return hash_hmac('sha256', 'store|' . $receiptCode, RECEIPT_SECRET);
}

function moneyFromCents(int $cents): string
{
    return 'R$ ' . number_format($cents / 100, 2, ',', '.');
}

function seedStoreProducts(PDO $pdo): void
{
    $exists = (int) $pdo->query('SELECT COUNT(*) FROM store_products')->fetchColumn();
    if ($exists > 0) {
        return;
    }

    $seed = [
        ['Vestuário', 'Camisa Oficial 2026', 'Modelo da temporada com escudo clássico e gola premium.', 8990, 'img/des.png'],
        ['Vestuário', 'Moletom Barbarus', 'Conforto total com tecido pesado e estampa em alto relevo.', 14990, 'img/participar.png'],
        ['Kits', 'Kit Torcedor', 'Camisa + boné + copo oficial com desconto especial.', 19990, 'img/ajudar.png'],
        ['Acessórios', 'Boné Trucker', 'Clássico do clã com ajuste fácil e logo bordado.', 5990, 'img/logo.png'],
    ];

    $stmt = $pdo->prepare(
        'INSERT INTO store_products
        (sector_name, name, description, price_cents, image_url, created_at, updated_at)
        VALUES (:sector, :name, :description, :price, :image, :created, :updated)'
    );
    $now = nowIso();
    foreach ($seed as [$sector, $name, $description, $price, $image]) {
        $stmt->execute([
            ':sector' => $sector,
            ':name' => $name,
            ':description' => $description,
            ':price' => $price,
            ':image' => $image,
            ':created' => $now,
            ':updated' => $now,
        ]);
    }
}

function productPriceMeta(array $product, bool $hasActiveMember): array
{
    $list = max(0, (int) ($product['price_cents'] ?? 0));
    $promo = (int) ($product['flash_promo_price_cents'] ?? 0);
    $discountPercent = max(0, min(90, (int) ($product['discount_percent'] ?? 0)));

    $base = $promo > 0 ? $promo : $list;
    $afterCampaign = $discountPercent > 0 ? (int) round($base * (1 - ($discountPercent / 100))) : $base;
    $final = $hasActiveMember ? (int) round($afterCampaign * 0.85) : $afterCampaign;

    return [
        'list_price_cents' => $list,
        'promo_price_cents' => $promo > 0 ? $promo : null,
        'discount_percent' => $discountPercent,
        'after_campaign_cents' => $afterCampaign,
        'final_price_cents' => $final,
    ];
}

function splitMediaLines(?string $text): array
{
    if (!$text) {
        return [];
    }
    $parts = preg_split('/\r\n|\r|\n/', $text) ?: [];
    $out = [];
    foreach ($parts as $p) {
        $v = trim($p);
        if ($v !== '') {
            $out[] = $v;
        }
    }
    return $out;
}
