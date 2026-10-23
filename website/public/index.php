<?php
declare(strict_types=1);
session_start();

/* Bep Nha - giao dien PHP thuần, UTF-8, không cần tải thư viện bên ngoài. */
$dbHost = getenv('DB_HOST') ?: 'mysql';
$dbName = getenv('DB_NAME') ?: 'congthuc';
$dbUser = getenv('DB_USER') ?: 'recipe_user';
$dbPass = getenv('DB_PASSWORD') ?: '';

function h(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Cắt chuỗi theo ký tự Unicode để không làm vỡ dấu tiếng Việt. */
function excerpt(?string $value, int $limit = 145): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($value, 'UTF-8') > $limit
            ? mb_substr($value, 0, $limit, 'UTF-8') . '…'
            : $value;
    }
    $chars = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
    if ($chars === false) {
        return $value;
    }
    return count($chars) > $limit ? implode('', array_slice($chars, 0, $limit)) . '…' : $value;
}

function connectDb(): PDO
{
    global $dbHost, $dbName, $dbUser, $dbPass;
    return new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function categoryIcon(string $category): string
{
    $value = strtolower($category); // Khong can mbstring de chay tren image PHP hien tai
    if (str_contains($value, 'chay')) return '🥬';
    if (str_contains($value, 'nước') || str_contains($value, 'pho') || str_contains($value, 'bún')) return '🍜';
    if (str_contains($value, 'tráng miệng') || str_contains($value, 'chè')) return '🍮';
    if (str_contains($value, 'uống')) return '🍹';
    if (str_contains($value, 'bánh')) return '🥐';
    return '🍲';
}

$error = '';
$recipes = [];
$selected = null;
$categories = [];
$q = trim((string) ($_GET['q'] ?? ''));
$categoryFilter = trim((string) ($_GET['category'] ?? ''));
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$form = ['title' => '', 'category' => 'Món chính', 'minutes' => '30', 'ingredients' => '', 'instructions' => ''];

try {
    $pdo = connectDb();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $form['title'] = trim((string) ($_POST['title'] ?? ''));
        $form['category'] = trim((string) ($_POST['category'] ?? 'Khác'));
        $form['minutes'] = (string) max(1, min(1440, (int) ($_POST['minutes'] ?? 30)));
        $form['ingredients'] = trim((string) ($_POST['ingredients'] ?? ''));
        $form['instructions'] = trim((string) ($_POST['instructions'] ?? ''));

        if ($form['title'] === '' || $form['ingredients'] === '' || $form['instructions'] === '') {
            $error = 'Vui lòng điền tên món ăn, nguyên liệu và cách làm trước khi lưu.';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO recipes (title, category, ingredients, instructions, minutes) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $form['title'],
                $form['category'] !== '' ? $form['category'] : 'Khác',
                $form['ingredients'],
                $form['instructions'],
                (int) $form['minutes'],
            ]);
            header('Location: /?saved=1');
            exit;
        }
    }

    $categories = $pdo->query('SELECT DISTINCT category FROM recipes ORDER BY category')->fetchAll(PDO::FETCH_COLUMN);

    if ($id) {
        $stmt = $pdo->prepare('SELECT * FROM recipes WHERE id = ?');
        $stmt->execute([$id]);
        $selected = $stmt->fetch() ?: null;
    }

    $where = [];
    $params = [];
    if ($q !== '') {
        $where[] = '(title LIKE ? OR category LIKE ? OR ingredients LIKE ?)';
        $term = '%' . $q . '%';
        array_push($params, $term, $term, $term);
    }
    if ($categoryFilter !== '') {
        $where[] = 'category = ?';
        $params[] = $categoryFilter;
    }
    $sql = 'SELECT id, title, category, ingredients, instructions, minutes, created_at FROM recipes';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY created_at DESC, id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $recipes = $stmt->fetchAll();
} catch (Throwable $e) {
    http_response_code(503);
    $error = 'Chưa kết nối được cơ sở dữ liệu. Vui lòng kiểm tra container MySQL và cấu hình .env.';
    error_log('Recipe application database error: ' . $e->getMessage());
}

$recipeCount = count($recipes);
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Bếp Nhà — Cảm hứng nấu ngon mỗi ngày</title>
    <style>
        :root {
            --green-950: #173b2e;
            --green-900: #1e4a38;
            --green-800: #285d45;
            --green-700: #347653;
            --green-100: #e8f1e8;
            --green-50: #f3f7ef;
            --cream: #fbf8f1;
            --paper: #fffefa;
            --ink: #26352c;
            --muted: #6d786f;
            --line: #e7e9df;
            --orange: #e39b59;
            --shadow: 0 18px 50px rgba(33, 55, 39, .08);
            --radius-xl: 26px;
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0;
            color: var(--ink);
            background: var(--cream);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Noto Sans", Arial, sans-serif;
            font-size: 16px;
            line-height: 1.65;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }
        a { color: inherit; text-decoration: none; }
        button, input, select, textarea { font: inherit; }
        button, a, input, select, textarea { -webkit-tap-highlight-color: transparent; }
        .wrap { width: min(1180px, calc(100% - 44px)); margin-inline: auto; }
        .topbar {
            position: relative;
            z-index: 2;
            border-bottom: 1px solid rgba(255,255,255,.13);
            background: var(--green-950);
            color: #fff;
        }
        .nav { min-height: 82px; display: flex; align-items: center; justify-content: space-between; gap: 24px; }
        .brand { display: inline-flex; align-items: center; gap: 12px; min-width: 0; }
        .brand-mark {
            width: 45px; height: 45px; flex: 0 0 45px; display: grid; place-items: center;
            border-radius: 15px; background: #d9e8c7; color: var(--green-950); font-size: 24px;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.3);
        }
        .brand-name { font-size: 21px; font-weight: 850; letter-spacing: -.6px; line-height: 1.15; }
        .brand-caption { display: block; margin-top: 4px; color: #c6d7ca; font-size: 11px; font-weight: 650; letter-spacing: 1.5px; text-transform: uppercase; }
        .nav-right { display: flex; align-items: center; gap: 10px; color: #e6eee5; font-size: 13px; }
        .nav-dot { width: 7px; height: 7px; border-radius: 50%; background: #b7d993; box-shadow: 0 0 0 4px rgba(183,217,147,.13); }
        .hero { overflow: hidden; position: relative; padding: 64px 0 58px; background: var(--green-950); color: #fff; }
        .hero::before, .hero::after { content: ""; position: absolute; pointer-events: none; border: 1px solid rgba(215,235,196,.15); border-radius: 50%; }
        .hero::before { width: 430px; height: 430px; right: -105px; top: -200px; }
        .hero::after { width: 310px; height: 310px; right: -46px; top: -140px; }
        .hero-grid { display: grid; grid-template-columns: minmax(0,1.2fr) minmax(260px,.8fr); align-items: center; gap: 42px; position: relative; z-index: 1; }
        .eyebrow { display: inline-flex; align-items: center; gap: 9px; padding: 7px 12px; border: 1px solid rgba(231,242,222,.2); border-radius: 99px; color: #dbe9d4; font-size: 12px; font-weight: 750; letter-spacing: 1.25px; text-transform: uppercase; }
        .eyebrow-star { color: #d3e7ad; font-size: 15px; }
        h1 { max-width: 670px; margin: 22px 0 17px; font-size: clamp(36px, 5vw, 58px); line-height: 1.08; font-weight: 800; letter-spacing: -2.4px; }
        h1 em { color: #d2e5b1; font-style: normal; }
        .hero-copy { max-width: 570px; margin: 0; color: #d4e0d5; font-size: 17px; line-height: 1.8; }
        .searchbar { display: flex; gap: 9px; max-width: 630px; margin-top: 30px; padding: 8px; border-radius: 17px; background: #fff; box-shadow: 0 14px 35px rgba(0,0,0,.15); }
        .search-icon { align-self: center; margin-left: 8px; color: #7b897e; font-size: 20px; }
        .searchbar input { min-width: 0; flex: 1; border: 0; outline: 0; padding: 10px 5px; color: var(--ink); background: transparent; }
        .searchbar input::placeholder { color: #9aa39b; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 46px; padding: 11px 18px; border: 0; border-radius: 12px; background: var(--green-800); color: #fff; font-weight: 750; cursor: pointer; transition: transform .18s ease, background .18s ease, box-shadow .18s ease; }
        .btn:hover { background: var(--green-700); transform: translateY(-1px); }
        .btn:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 3px solid #e7b777; outline-offset: 3px; }
        .btn-light { background: #dbe8c8; color: var(--green-950); }
        .btn-light:hover { background: #e8f1d9; }
        .hero-art { display: grid; place-items: center; min-height: 300px; }
        .plate { display: grid; place-items: center; width: clamp(220px, 26vw, 310px); aspect-ratio: 1; position: relative; border-radius: 50%; background: #f7eedf; border: 12px solid rgba(255,255,255,.22); box-shadow: 0 24px 55px rgba(0,0,0,.23), inset 0 0 0 2px #e4d6bd; transform: rotate(-7deg); }
        .plate-inner { display: grid; place-items: center; width: 78%; aspect-ratio: 1; border-radius: 50%; background: #fffdf5; border: 1px solid #e8deca; box-shadow: inset 0 0 0 8px #f7f0e2; font-size: clamp(78px, 10vw, 115px); }
        .art-note { position: absolute; right: -20px; bottom: 17px; padding: 13px 16px; border-radius: 15px; color: var(--ink); background: #fffefa; box-shadow: var(--shadow); transform: rotate(7deg); }
        .art-note strong { display: block; font-size: 13px; }
        .art-note span { color: var(--muted); font-size: 11px; }
        .hero-meta { display: flex; flex-wrap: wrap; gap: 22px; margin-top: 23px; color: #c8d8ca; font-size: 13px; }
        .hero-meta span { display: inline-flex; align-items: center; gap: 8px; }
        .hero-meta b { color: #fff; }
        .main { padding: 40px 0 72px; }
        .flash { display: flex; align-items: flex-start; gap: 12px; margin: 0 0 22px; padding: 15px 18px; border: 1px solid #cce4cb; border-radius: 15px; background: #eff8ed; color: #285b38; }
        .flash.error { border-color: #f0cbc3; background: #fff1ed; color: #8b3126; }
        .flash-icon { font-size: 20px; line-height: 1.4; }
        .content-grid { display: grid; grid-template-columns: minmax(0,1fr) 350px; align-items: start; gap: 28px; }
        .section-head { display: flex; align-items: end; justify-content: space-between; gap: 20px; margin: 0 0 19px; }
        .section-kicker { margin: 0 0 6px; color: var(--green-700); font-size: 11px; font-weight: 850; letter-spacing: 1.8px; text-transform: uppercase; }
        h2 { margin: 0; color: var(--ink); font-size: clamp(24px, 3vw, 31px); line-height: 1.2; letter-spacing: -1px; }
        .result-count { white-space: nowrap; color: var(--muted); font-size: 13px; }
        .filters { display: flex; flex-wrap: wrap; gap: 8px; margin: 0 0 24px; }
        .chip { display: inline-flex; align-items: center; gap: 7px; min-height: 36px; padding: 7px 13px; border: 1px solid #e3e7de; border-radius: 99px; color: #5d6b60; background: rgba(255,255,255,.55); font-size: 12px; font-weight: 700; transition: all .18s ease; }
        .chip:hover, .chip.active { border-color: var(--green-800); background: var(--green-800); color: #fff; }
        .recipes { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 17px; }
        .recipe-card { overflow: hidden; display: flex; flex-direction: column; min-width: 0; border: 1px solid var(--line); border-radius: 20px; background: var(--paper); box-shadow: 0 5px 20px rgba(29,49,34,.025); transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
        .recipe-card:hover { transform: translateY(-3px); border-color: #d7e1d2; box-shadow: var(--shadow); }
        .recipe-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 112px; padding: 21px 22px; background: linear-gradient(135deg, #edf3e7, #f8f1e5); }
        .food-icon { display: grid; place-items: center; width: 67px; height: 67px; flex: 0 0 67px; border: 1px solid rgba(41,83,55,.07); border-radius: 22px; background: rgba(255,255,255,.72); font-size: 34px; }
        .category-label { display: inline-flex; align-items: center; max-width: 100%; padding: 5px 10px; border-radius: 99px; background: #eaf2e5; color: #376a49; font-size: 11px; line-height: 1.3; font-weight: 800; }
        .recipe-content { display: flex; flex: 1; flex-direction: column; padding: 20px 21px 21px; }
        .recipe-content h3 { margin: 0 0 10px; font-size: 19px; line-height: 1.35; letter-spacing: -.45px; overflow-wrap: anywhere; }
        .recipe-content h3 a:hover { color: var(--green-700); }
        .recipe-content p { display: -webkit-box; overflow: hidden; margin: 0; color: var(--muted); font-size: 13px; line-height: 1.75; overflow-wrap: anywhere; -webkit-line-clamp: 3; -webkit-box-orient: vertical; }
        .recipe-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 12px; margin-top: auto; padding: 19px 0 14px; color: #788279; font-size: 12px; }
        .recipe-meta span { display: inline-flex; align-items: center; gap: 5px; }
        .recipe-link { display: inline-flex; align-items: center; gap: 8px; color: var(--green-800); font-size: 13px; font-weight: 850; }
        .recipe-link:hover { gap: 12px; }
        .empty-state { grid-column: 1 / -1; padding: 42px 28px; border: 1px dashed #d7dfd3; border-radius: 20px; background: rgba(255,255,255,.6); text-align: center; }
        .empty-icon { margin-bottom: 12px; font-size: 42px; }
        .empty-state h3 { margin: 0 0 6px; font-size: 19px; }
        .empty-state p { margin: 0 auto 18px; color: var(--muted); font-size: 14px; }
        .side-card { position: sticky; top: 18px; overflow: hidden; border: 1px solid var(--line); border-radius: 22px; background: var(--paper); box-shadow: var(--shadow); }
        .form-head { padding: 23px 24px 20px; background: var(--green-900); color: #fff; }
        .form-head .section-kicker { color: #caddb6; }
        .form-head h2 { color: #fff; font-size: 24px; }
        .form-head p { margin: 8px 0 0; color: #d0dfd2; font-size: 13px; line-height: 1.6; }
        .form-body { padding: 22px 24px 24px; }
        .field { margin-bottom: 15px; }
        .field label { display: block; margin: 0 0 7px; color: #415147; font-size: 12px; font-weight: 800; }
        .required { color: #bb6348; }
        .field input, .field select, .field textarea { display: block; width: 100%; min-width: 0; border: 1px solid #dfe4da; border-radius: 11px; outline: none; padding: 11px 12px; color: var(--ink); background: #fff; transition: border-color .15s ease, box-shadow .15s ease; font-size: 13px; }
        .field input, .field select { min-height: 44px; }
        .field textarea { resize: vertical; min-height: 96px; line-height: 1.6; }
        .field input:focus, .field select:focus, .field textarea:focus { border-color: #85a88a; box-shadow: 0 0 0 3px rgba(67,127,80,.1); }
        .field input::placeholder, .field textarea::placeholder { color: #a0a9a0; }
        .btn-submit { width: 100%; min-height: 48px; margin-top: 2px; }
        .form-note { display: flex; gap: 8px; margin-top: 14px; color: #869087; font-size: 11px; line-height: 1.5; }
        .detail { margin: 0 0 28px; padding: 29px; border: 1px solid var(--line); border-radius: 23px; background: var(--paper); box-shadow: var(--shadow); }
        .back-link { display: inline-flex; gap: 8px; margin-bottom: 24px; color: var(--green-800); font-size: 13px; font-weight: 800; }
        .detail-title-row { display: flex; align-items: flex-start; gap: 18px; }
        .detail-emoji { display: grid; place-items: center; width: 82px; height: 82px; flex: 0 0 82px; border-radius: 24px; background: var(--green-50); font-size: 42px; }
        .detail h2 { margin: 10px 0 12px; font-size: clamp(28px, 4vw, 38px); overflow-wrap: anywhere; }
        .detail-description { max-width: 690px; color: var(--muted); font-size: 14px; }
        .detail-columns { display: grid; grid-template-columns: .8fr 1.2fr; gap: 18px; margin-top: 28px; }
        .detail-box { min-width: 0; padding: 22px; border-radius: 17px; background: #f7f8f2; }
        .detail-box h3 { margin: 0 0 12px; color: var(--green-900); font-size: 16px; }
        .detail-text { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; color: #536258; font-size: 14px; line-height: 1.9; }
        .footer { padding: 30px 0; border-top: 1px solid #e7e8df; color: #7d877e; background: #f6f3eb; }
        .footer-inner { display: flex; align-items: center; justify-content: space-between; gap: 20px; }
        .footer-brand { color: var(--green-900); font-weight: 850; }
        .footer small { font-size: 12px; }
        @media (max-width: 960px) {
            .content-grid { grid-template-columns: minmax(0,1fr) 320px; gap: 18px; }
            .hero-grid { gap: 18px; }
            .recipe-top { padding: 16px; }
            .recipe-content { padding: 17px; }
            .form-head, .form-body { padding-inline: 20px; }
        }
        @media (max-width: 760px) {
            .wrap { width: min(100% - 28px, 600px); }
            .nav { min-height: 70px; }
            .brand-mark { width: 40px; height: 40px; flex-basis: 40px; border-radius: 13px; }
            .brand-name { font-size: 19px; }
            .nav-right { font-size: 0; gap: 0; }
            .hero { padding: 42px 0 38px; }
            .hero-grid { grid-template-columns: 1fr; }
            .hero-art { display: none; }
            h1 { max-width: 560px; margin-top: 18px; font-size: clamp(35px, 9vw, 49px); letter-spacing: -1.7px; }
            .hero-copy { font-size: 15px; }
            .searchbar { margin-top: 23px; }
            .searchbar .btn { padding-inline: 14px; }
            .hero-meta { gap: 12px 17px; }
            .main { padding: 30px 0 45px; }
            .content-grid { grid-template-columns: 1fr; }
            .side-card { position: static; grid-row: 1; }
            .recipes { grid-template-columns: repeat(2, minmax(0,1fr)); gap: 12px; }
            .recipe-top { min-height: 90px; padding: 13px; }
            .food-icon { width: 52px; height: 52px; flex-basis: 52px; border-radius: 17px; font-size: 27px; }
            .recipe-content { padding: 15px; }
            .recipe-content h3 { font-size: 16px; }
            .recipe-content p { font-size: 12px; }
            .recipe-meta { gap: 8px; margin-top: 6px; padding-top: 14px; }
            .detail { padding: 20px; }
            .detail-columns { grid-template-columns: 1fr; }
            .footer-inner { flex-direction: column; align-items: flex-start; gap: 5px; }
        }
        @media (max-width: 430px) {
            .wrap { width: calc(100% - 24px); }
            .brand-caption { font-size: 9px; letter-spacing: 1px; }
            .searchbar { gap: 4px; padding: 6px; }
            .searchbar input { font-size: 13px; }
            .searchbar .btn { min-height: 42px; padding: 8px 10px; font-size: 12px; }
            .recipes { grid-template-columns: 1fr; }
            .recipe-top { min-height: 100px; }
            .section-head { align-items: flex-start; flex-direction: column; gap: 5px; }
            .detail-title-row { flex-direction: column; }
        }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; }
        }
    </style>
</head>
<body>
<header class="topbar">
    <div class="wrap nav">
        <a class="brand" href="/" aria-label="Bếp Nhà - Trang chủ">
            <span class="brand-mark" aria-hidden="true">♨</span>
            <span><span class="brand-name">Bếp Nhà</span><span class="brand-caption">Nấu ngon từ điều giản dị</span></span>
        </a>
        <div class="nav-right"><span class="nav-dot" aria-hidden="true"></span><span>Cảm hứng cho bữa cơm nhà</span></div>
    </div>
</header>

<section class="hero">
    <div class="wrap hero-grid">
        <div>
            <span class="eyebrow"><span class="eyebrow-star">✦</span> Góc nhỏ của người yêu bếp</span>
            <h1>Mỗi bữa ăn là một câu chuyện <em>ấm lòng.</em></h1>
            <p class="hero-copy">Khám phá công thức gần gũi, nguyên liệu dễ tìm và cách làm rõ ràng để mỗi ngày vào bếp đều trở thành niềm vui.</p>
            <form class="searchbar" method="get" action="/" role="search">
                <span class="search-icon" aria-hidden="true">⌕</span>
                <input type="search" name="q" value="<?= h($q) ?>" aria-label="Tìm công thức" placeholder="Bạn muốn nấu món gì hôm nay?">
                <?php if ($categoryFilter !== ''): ?><input type="hidden" name="category" value="<?= h($categoryFilter) ?>"><?php endif; ?>
                <button class="btn" type="submit">Tìm món <span aria-hidden="true">→</span></button>
            </form>
            <div class="hero-meta">
                <span>✦ <b><?= count($categories) ?></b> danh mục món ăn</span>
                <span>♡ Công thức thân thuộc</span>
                <span>◷ Dễ làm mỗi ngày</span>
            </div>
        </div>
        <div class="hero-art" aria-hidden="true">
            <div class="plate"><div class="plate-inner">🍲</div><div class="art-note"><strong>Hương vị nhà làm</strong><span>Ngon lành · Gần gũi · Chân thành</span></div></div>
        </div>
    </div>
</section>

<main class="main">
    <div class="wrap">
        <?php if (isset($_GET['saved'])): ?>
            <div class="flash"><span class="flash-icon" aria-hidden="true">✓</span><div><strong>Đã lưu công thức!</strong><br> Món ăn của bạn đã được thêm vào danh sách.</div></div>
        <?php endif; ?>
        <?php if ($error !== ''): ?>
            <div class="flash error"><span class="flash-icon" aria-hidden="true">!</span><div><?= h($error) ?></div></div>
        <?php endif; ?>

        <?php if ($selected): ?>
            <article class="detail">
                <a class="back-link" href="/?<?= $q !== '' ? 'q=' . rawurlencode($q) . '&' : '' ?><?= $categoryFilter !== '' ? 'category=' . rawurlencode($categoryFilter) : '' ?>">← Quay lại công thức</a>
                <div class="detail-title-row">
                    <div class="detail-emoji" aria-hidden="true"><?= h(categoryIcon((string) $selected['category'])) ?></div>
                    <div>
                        <span class="category-label"><?= h((string) $selected['category']) ?></span>
                        <h2><?= h((string) $selected['title']) ?></h2>
                        <div class="recipe-meta" style="margin:0;padding:0"><span>◷ <?= (int) $selected['minutes'] ?> phút</span><span>✦ Công thức Bếp Nhà</span></div>
                    </div>
                </div>
                <p class="detail-description">Chuẩn bị nguyên liệu, thực hiện từng bước và điều chỉnh gia vị theo khẩu vị gia đình bạn.</p>
                <div class="detail-columns">
                    <section class="detail-box"><h3>🧺 Nguyên liệu cần có</h3><p class="detail-text"><?= h((string) $selected['ingredients']) ?></p></section>
                    <section class="detail-box"><h3>♨ Cách thực hiện</h3><p class="detail-text"><?= h((string) $selected['instructions']) ?></p></section>
                </div>
            </article>
        <?php endif; ?>

        <div class="content-grid">
            <section aria-labelledby="recipe-heading">
                <div class="section-head">
                    <div><p class="section-kicker">Từ căn bếp nhỏ</p><h2 id="recipe-heading"><?= $q !== '' || $categoryFilter !== '' ? 'Khám phá công thức' : 'Món ngon được chia sẻ' ?></h2></div>
                    <span class="result-count"><?= $recipeCount ?> công thức<?= $recipeCount === 1 ? '' : '' ?></span>
                </div>

                <nav class="filters" aria-label="Lọc theo danh mục">
                    <a class="chip <?= $categoryFilter === '' ? 'active' : '' ?>" href="/?<?= $q !== '' ? 'q=' . rawurlencode($q) : '' ?>">✦ Tất cả món</a>
                    <?php foreach ($categories as $cat): ?>
                        <a class="chip <?= $categoryFilter === (string) $cat ? 'active' : '' ?>" href="/?<?= $q !== '' ? 'q=' . rawurlencode($q) . '&' : '' ?>category=<?= rawurlencode((string) $cat) ?>"><?= h(categoryIcon((string) $cat)) ?> <?= h((string) $cat) ?></a>
                    <?php endforeach; ?>
                </nav>

                <div class="recipes">
                    <?php if (!$recipes): ?>
                        <div class="empty-state">
                            <div class="empty-icon" aria-hidden="true">🍃</div>
                            <h3>Chưa tìm thấy món phù hợp</h3>
                            <p>Thử một từ khóa khác hoặc chia sẻ công thức đầu tiên cho căn bếp này nhé.</p>
                            <a class="btn" href="/">Xem tất cả công thức</a>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recipes as $r): ?>
                            <article class="recipe-card">
                                <div class="recipe-top">
                                    <span class="category-label"><?= h((string) $r['category']) ?></span>
                                    <span class="food-icon" aria-hidden="true"><?= h(categoryIcon((string) $r['category'])) ?></span>
                                </div>
                                <div class="recipe-content">
                                    <h3><a href="/?id=<?= (int) $r['id'] ?><?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?><?= $categoryFilter !== '' ? '&category=' . rawurlencode($categoryFilter) : '' ?>"><?= h((string) $r['title']) ?></a></h3>
                                    <p><?= h(excerpt((string) $r['ingredients'])) ?></p>
                                    <div class="recipe-meta"><span>◷ <?= (int) $r['minutes'] ?> phút</span><span>♧ Nguyên liệu đơn giản</span></div>
                                    <a class="recipe-link" href="/?id=<?= (int) $r['id'] ?><?= $q !== '' ? '&q=' . rawurlencode($q) : '' ?><?= $categoryFilter !== '' ? '&category=' . rawurlencode($categoryFilter) : '' ?>">Xem công thức <span aria-hidden="true">→</span></a>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <aside class="side-card" id="them-cong-thuc">
                <div class="form-head"><p class="section-kicker">Cùng nhau sẻ chia</p><h2>Thêm món nhà làm</h2><p>Ghi lại công thức yêu thích để ai cũng có thể nấu ngon hơn mỗi ngày.</p></div>
                <form class="form-body" method="post" action="/">
                    <div class="field"><label for="title">Tên món ăn <span class="required">*</span></label><input id="title" name="title" value="<?= h($form['title']) ?>" maxlength="180" required placeholder="Ví dụ: Canh chua cá lóc"></div>
                    <div class="field"><label for="category">Danh mục</label><select id="category" name="category">
                        <?php
                        $options = ['Món chính', 'Món chay', 'Món nước', 'Món tráng miệng', 'Đồ uống', 'Bánh', 'Khác'];
                        foreach (array_unique(array_merge($options, $categories)) as $option):
                        ?>
                            <option value="<?= h((string) $option) ?>" <?= $form['category'] === (string) $option ? 'selected' : '' ?>><?= h((string) $option) ?></option>
                        <?php endforeach; ?>
                    </select></div>
                    <div class="field"><label for="minutes">Thời gian chuẩn bị (phút)</label><input id="minutes" name="minutes" type="number" min="1" max="1440" value="<?= h($form['minutes']) ?>" required></div>
                    <div class="field"><label for="ingredients">Nguyên liệu <span class="required">*</span></label><textarea id="ingredients" name="ingredients" rows="4" required placeholder="Ghi rõ nguyên liệu và định lượng..."><?= h($form['ingredients']) ?></textarea></div>
                    <div class="field"><label for="instructions">Cách làm <span class="required">*</span></label><textarea id="instructions" name="instructions" rows="5" required placeholder="Bước 1: Sơ chế nguyên liệu...&#10;Bước 2: Bắt đầu chế biến..."><?= h($form['instructions']) ?></textarea></div>
                    <button class="btn btn-submit" type="submit">Lưu công thức <span aria-hidden="true">↗</span></button>
                    <div class="form-note"><span aria-hidden="true">🔒</span><span>Công thức được lưu vào cơ sở dữ liệu của ứng dụng.</span></div>
                </form>
            </aside>
        </div>
    </div>
</main>

<footer class="footer"><div class="wrap footer-inner"><span class="footer-brand">♨ Bếp Nhà</span><small>Gìn giữ hương vị gia đình, lan tỏa niềm vui vào bếp.</small><small>© <?= date('Y') ?> · Công thức nấu ăn</small></div></footer>
</body>
</html>
