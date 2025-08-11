<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
apply_security_headers();
start_secure_session();

// Zapis wiadomości (POST) - wymagamy kategorii i ustawiamy approved=0 (moderacja)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_message') {
    if (!csrf_check($_POST['csrf'] ?? '')) { http_response_code(400); die('CSRF'); }
    $content = trim($_POST['content'] ?? '');
    $author  = trim($_POST['author'] ?? '');
    $cat     = (int)($_POST['category_id'] ?? 0);
    if ($content === '') { http_response_code(422); die('Brak treści'); }
    if ($cat <= 0) { http_response_code(422); die('Wybierz kategorię'); }
    $stmt = db()->prepare('INSERT INTO entries (category_id, content, author, approved) VALUES (:cat, :content, NULLIF(:author, ""), 0)');
    $stmt->execute([':cat' => $cat, ':content' => $content, ':author' => $author]);
    header('Location: ' . base_url() . '/?posted=1');
    exit;
}

// Ajax: list entries by category - tylko approved=1
if (($_GET['ajax'] ?? '') === 'entries') {
    header('Content-Type: application/json; charset=utf-8');
    $category = trim($_GET['category'] ?? 'Wszystkie');
    if ($category === 'Wszystkie') {
        $q = db()->query('SELECT e.*, c.name as category FROM entries e LEFT JOIN categories c ON c.id=e.category_id WHERE e.approved=1 ORDER BY e.created_at DESC LIMIT 200');
        echo json_encode($q->fetchAll());
    } else {
        $stmt = db()->prepare('SELECT e.*, c.name as category FROM entries e LEFT JOIN categories c ON c.id=e.category_id WHERE e.approved=1 AND c.name = :name ORDER BY e.created_at DESC LIMIT 200');
        $stmt->execute([':name' => $category]);
        echo json_encode($stmt->fetchAll());
    }
    exit;
}

// Random redirect endpoints
if (($_GET['go'] ?? '') !== '') {
    $bucket = $_GET['go'];
    $stmt = db()->prepare('SELECT url FROM random_links WHERE bucket=:b AND active=1 ORDER BY RAND() LIMIT 1');
    $stmt->execute([':b' => $bucket]);
    $row = $stmt->fetch();
    if ($row) { header('Location: ' . $row['url'], true, 302); } else { echo 'Brak linków w tej kategorii.'; }
    exit;
}

$csrf = csrf_token();
?><!doctype html>
<html lang="pl" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Kapsuła Czasu 2112</title>
<link rel="preconnect" href="/" crossorigin>
<style>
  :root{
    --radius:24px; --blur:16px; --t:300ms; --gap:16px;
    --light-bg:#f6f7f9; --light-surface:#ffffffcc; --light-text:#0b1220; --light-accent:#0f62fe;
    --dark-bg:#0a0a0a; --dark-surface:#0b0b0bcc; --dark-text:#f3f3f3; --dark-accent:#d4af37;
  }
  html{scroll-behavior:smooth}
  body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"SF Pro Display","Inter",Segoe UI,Roboto,Ubuntu,system-ui,Arial,sans-serif; background:var(--light-bg); color:var(--light-text);}
  [data-theme="dark"] body{background:var(--dark-bg); color:var(--dark-text)}
  .wrap{max-width:1100px;margin:0 auto;padding:24px}
  header{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0}
  .brand h1{margin:0;font-size:clamp(24px,5vw,40px);letter-spacing:-0.02em}
  .brand p{margin:.25rem 0 0;opacity:.8}
  .sub a{display:inline-block;margin-top:6px;text-decoration:none}
  .theme-toggle{position:relative;display:inline-flex;align-items:center;gap:8px;background:var(--light-surface);backdrop-filter:blur(var(--blur));border-radius:999px;padding:8px 12px;box-shadow:0 10px 30px rgba(0,0,0,.08);cursor:pointer;transition:all var(--t)}
  [data-theme="dark"] .theme-toggle{background:var(--dark-surface);box-shadow:0 10px 30px rgba(0,0,0,.5)}
  .toggle-dot{width:22px;height:22px;border-radius:50%;background:linear-gradient(180deg,#fff,#ddd); transform:translateX(0);transition:transform var(--t)}
  [data-theme="dark"] .toggle-dot{background:linear-gradient(180deg,#d4af37,#a88018);transform:translateX(24px)}

  .hero{margin-top:12px;display:grid;gap:16px}
  .cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
  .card{position:relative;border-radius:var(--radius);padding:24px;backdrop-filter:blur(var(--blur));
        background:linear-gradient(135deg,rgba(255,255,255,.9),rgba(255,255,255,.65));
        box-shadow:0 20px 40px rgba(0,0,0,.08);transition:transform .25s ease, box-shadow .25s ease}
  .card h3{margin:0 0 8px;font-size:14px;opacity:.8;font-weight:600;letter-spacing:.08em;text-transform:uppercase}
  .card .value{font-size:clamp(28px,6vw,54px);font-weight:700;letter-spacing:-0.02em}
  .card:hover{transform:translateY(-4px);box-shadow:0 30px 60px rgba(0,0,0,.12)}
  [data-theme="dark"] .card{background:linear-gradient(135deg,rgba(20,20,20,.9),rgba(20,20,20,.6));box-shadow:0 20px 50px rgba(0,0,0,.55)}

  .cta{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;margin:22px 0}
  .btn{
     display:inline-block;text-align:center;text-decoration:none;
     border:none;border-radius:18px;padding:18px 18px;font-size:16px;font-weight:600;letter-spacing:.3px;cursor:pointer;
     background:linear-gradient(180deg,#ffffff,#e9eefc); color:#0b1220; box-shadow:inset 0 1px 0 rgba(255,255,255,.5),0 14px 30px rgba(0,20,80,.12);
     transition:transform .15s ease, box-shadow .2s ease
  }
  .btn:hover{transform:translateY(-2px);box-shadow:0 20px 40px rgba(0,20,80,.18)}
  [data-theme="dark"] .btn{background:linear-gradient(180deg,#1a1a1a,#0f0f0f); color:#f3f3f3; box-shadow:inset 0 1px 0 rgba(255,255,255,.06),0 16px 36px rgba(212,175,55,.25)}

  .panel{border-radius:24px;padding:24px;backdrop-filter:blur(var(--blur));background:linear-gradient(135deg,rgba(255,255,255,.9),rgba(255,255,255,.65));box-shadow:0 20px 40px rgba(0,0,0,.08)}
  [data-theme="dark"] .panel{background:linear-gradient(135deg,rgba(20,20,20,.9),rgba(20,20,20,.6));box-shadow:0 20px 60px rgba(0,0,0,.55)}
  .tabs{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px}
  .tab{padding:10px 14px;border-radius:12px;cursor:pointer;font-weight:600;opacity:.8}
  .tab.active{opacity:1; background:rgba(15,98,254,.12);color:#0f62fe}
  [data-theme="dark"] .tab.active{background:rgba(212,175,55,.16);color:#d4af37}

  .list{display:grid;gap:12px}
  .entry{padding:16px;border-radius:18px;background:rgba(255,255,255,.6);backdrop-filter:blur(12px);box-shadow:0 10px 24px rgba(0,0,0,.06)}
  [data-theme="dark"] .entry{background:rgba(20,20,20,.6);box-shadow:0 12px 30px rgba(0,0,0,.5)}
  .entry small{opacity:.7}
  textarea, input, select{width:100%;border-radius:14px;border:1px solid rgba(0,0,0,.08);padding:12px;background:rgba(255,255,255,.8)}
  [data-theme="dark"] textarea,[data-theme="dark"] input,[data-theme="dark"] select{background:rgba(25,25,25,.8);border-color:rgba(255,255,255,.12);color:#fff}
  form .row{display:grid;gap:12px}
  @media (max-width:720px){ .cards{grid-template-columns:1fr} }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div class="brand">
      <h1>Kapsuła Czasu 2112</h1>
      <p>W świecie clickbaitów i miernych prowokacji – miejsce dla rzeczy, które mają znaczenie</p>
      <div class="sub"><a href="#wall">Co z dzisiejszego internetu zasługuje na przetrwanie 100 lat?</a></div>
    </div>
    <button id="theme" class="theme-toggle" aria-label="Przełącz motyw">
      <span>☀️</span>
      <div class="toggle-dot"></div>
      <span>🌙</span>
    </button>
  </header>

  <section class="hero">
    <div class="cards" id="countdown">
      <div class="card"><h3>Miesiące</h3><div class="value" id="c-months">—</div></div>
      <div class="card"><h3>Tygodnie</h3><div class="value" id="c-weeks">—</div></div>
      <div class="card"><h3>Dni</h3><div class="value" id="c-days">—</div></div>
    </div>

    <div class="cta">
      <a class="btn" href="?go=read" target="_blank" rel="noopener noreferrer">Chcę przeczytać coś losowego</a>
      <a class="btn" href="?go=watch" target="_blank" rel="noopener noreferrer">Chcę obejrzeć coś losowego</a>
      <a class="btn" href="?go=listen" target="_blank" rel="noopener noreferrer">Chcę posłuchać czegoś losowego</a>
      <a class="btn" href="?go=absurd" target="_blank" rel="noopener noreferrer">Chcę coś absurdalnego</a>
    </div>
  </section>

  <section id="wall" class="panel">
    <h2>Ściana Wiadomości</h2>
    <form method="post" style="margin:12px 0 18px">
      <input type="hidden" name="action" value="add_message">
      <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
      <div class="row">
        <textarea name="content" rows="4" placeholder="Twoja wiadomość (wymagane)" required></textarea>
        <input name="author" placeholder="Autor (opcjonalnie)">
        <select name="category_id" required>
          <option value="" selected disabled>Wybierz kategorię</option>
          <?php $cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
          foreach ($cats as $c): if ($c['name']==='Wszystkie') continue; ?>
            <option value="<?= (int)$c['id'] ?>"><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Wyślij</button>
      </div>
    </form>

    <div class="tabs" id="tabs">
      <?php foreach ($cats as $c): ?>
        <div class="tab" data-cat="<?= e($c['name']) ?>"><?= e($c['name']) ?></div>
      <?php endforeach; ?>
    </div>
    <div id="entries" class="list" aria-live="polite"></div>
    <small style="opacity:.7">Nowe wpisy trafiają do moderacji i pojawiają się po akceptacji.</small>
  </section>

  <footer style="opacity:.6;margin:22px 0">© <?= date('Y') ?> Kapsuła Czasu 2112</footer>
</div>
<script src="/assets/app.js"></script>
</body>
</html>
