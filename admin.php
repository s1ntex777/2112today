<?php
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db.php';
apply_security_headers();
start_secure_session();

if (!is_admin()) {
  if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['do']??'')==='login') {
    if (!csrf_check($_POST['csrf']??'')) die('CSRF');
    if (!rate_limit_login()) { die('Za dużo prób. Odczekaj chwilę.'); }
    $pass = $_POST['password'] ?? '';
    if (ADMIN_PASS_HASH === '') die('Skonfiguruj ADMIN_PASS_HASH w config.php');
    if (password_verify($pass, ADMIN_PASS_HASH)) {
      $_SESSION['admin']=true; session_regenerate_id(true);
      header('Location: admin.php'); exit;
    } else { $err = 'Błędne hasło'; }
  }
  $csrf = csrf_token();
  ?>
  <!doctype html><meta charset="utf-8"><title>Panel admina — logowanie</title>
  <style>body{font-family:system-ui;display:grid;place-items:center;height:100dvh;background:#0b0b0b;color:#fff}
  form{background:#151515;padding:28px;border-radius:16px;min-width:320px;box-shadow:0 20px 60px rgba(0,0,0,.5)}
  input,button{width:100%;padding:12px;border-radius:12px;border:1px solid #333;background:#0f0f0f;color:#fff}
  button{margin-top:10px;background:#d4af37;border:none;font-weight:700}
  .err{color:#ffb4b4;margin:8px 0}
  </style>
  <form method="post" action="admin.php">
    <h2>Panel administratora</h2>
    <?php if(!empty($err)) echo '<div class="err">'.e($err).'</div>'; ?>
    <input type="hidden" name="do" value="login">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input name="password" type="password" placeholder="Hasło" required>
    <button>Zaloguj</button>
  </form>
  <?php exit; }

$csrf = csrf_token();
$view = $_GET['view'] ?? 'links';

// Kategorie
if (($_POST['do'] ?? '') === 'add_category') {
    if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
    $name = trim($_POST['name'] ?? '');
    if ($name && $name !== 'Wszystkie') {
        $stmt = db()->prepare('INSERT IGNORE INTO categories(name) VALUES(:n)');
        $stmt->execute([':n'=>$name]);
    }
    header('Location: admin.php?view=categories'); exit;
}
if (($_POST['do'] ?? '') === 'del_category') {
    if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
    $id = (int)($_POST['id'] ?? 0);
    if ($id) db()->prepare('DELETE FROM categories WHERE id=:id AND name<>"Wszystkie"')->execute([':id'=>$id]);
    header('Location: admin.php?view=categories'); exit;
}

// Wpisy
if (($_POST['do'] ?? '') === 'del_entry') {
    if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
    $id = (int)($_POST['id'] ?? 0);
    if ($id) db()->prepare('DELETE FROM entries WHERE id=:id')->execute([':id'=>$id]);
    header('Location: admin.php?view=entries'); exit;
}
if (($_POST['do'] ?? '') === 'approve_entry') {
    if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
    $id = (int)($_POST['id'] ?? 0);
    if ($id) db()->prepare('UPDATE entries SET approved=1 WHERE id=:id')->execute([':id'=>$id]);
    header('Location: admin.php?view=entries'); exit;
}
if (($_POST['do'] ?? '') === 'hide_entry') {
    if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
    $id = (int)($_POST['id'] ?? 0);
    if ($id) db()->prepare('UPDATE entries SET approved=0 WHERE id=:id')->execute([':id'=>$id]);
    header('Location: admin.php?view=entries'); exit;
}

// Losowe linki
if (($_POST['do'] ?? '') === 'add_links') {
    if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
    $bucket = $_POST['bucket'] ?? 'read';
    $bulk = trim($_POST['bulk'] ?? '');
    $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $bulk)));
    $pdo = db();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('INSERT INTO random_links(bucket,url) VALUES(:b,:u)');
    foreach ($lines as $u) {
        if (!filter_var($u, FILTER_VALIDATE_URL)) continue;
        $stmt->execute([':b'=>$bucket, ':u'=>$u]);
    }
    $pdo->commit();
    header('Location: admin.php?view=links&ok=1'); exit;
}
if (($_POST['do'] ?? '') === 'toggle_link') {
    if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
    $id = (int)($_POST['id'] ?? 0); $active=(int)($_POST['active'] ?? 0);
    db()->prepare('UPDATE random_links SET active=:a WHERE id=:id')->execute([':a'=>$active, ':id'=>$id]);
    header('Location: admin.php?view=links'); exit;
}
if (($_POST['do'] ?? '') === 'del_link') {
    if (!csrf_check($_POST['csrf'] ?? '')) die('CSRF');
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM random_links WHERE id=:id')->execute([':id'=>$id]);
    header('Location: admin.php?view=links'); exit;
}

if (($_GET['logout'] ?? '')==='1') { session_destroy(); header('Location: admin.php'); exit; }

?><!doctype html>
<html lang="pl"><meta charset="utf-8"><title>Admin — Kapsuła Czasu 2112</title>
<style>
body{margin:0;font-family:system-ui;background:#0b0b0b;color:#f3f3f3}
nav{display:flex;gap:8px;padding:12px;background:#111;position:sticky;top:0}
nav a{color:#ddd;text-decoration:none;padding:8px 12px;border-radius:8px}
nav a.active{background:#1b1b1b;color:#d4af37}
.container{max-width:1100px;margin:0 auto;padding:18px}
section{background:#121212;border:1px solid #1f1f1f;border-radius:16px;padding:18px;margin-bottom:16px}
input,textarea,select,button{padding:10px;border-radius:10px;border:1px solid #333;background:#0f0f0f;color:#fff}
button{background:#d4af37;border:none;font-weight:700}
table{width:100%;border-collapse:collapse}
th,td{padding:8px;border-bottom:1px solid #1f1f1f}
.badge{border:1px solid #444;padding:2px 8px;border-radius:999px}
.state{font-size:12px;opacity:.9;padding:2px 8px;border-radius:999px;border:1px solid #2e2e2e}
.state.pending{color:#ffce7a}
.state.approved{color:#8fd48a}
</style>
<body>
<nav>
  <a href="admin.php?view=links" class="<?= $view==='links'?'active':'' ?>">Losowe linki</a>
  <a href="admin.php?view=entries" class="<?= $view==='entries'?'active':'' ?>">Wpisy</a>
  <a href="admin.php?view=categories" class="<?= $view==='categories'?'active':'' ?>">Kategorie</a>
  <a href="admin.php?logout=1" style="margin-left:auto">Wyloguj</a>
</nav>
<div class="container">
<?php if ($view==='links'): ?>
<section>
  <h2>Masowe dodawanie linków</h2>
  <form method="post" action="admin.php?view=links">
    <input type="hidden" name="do" value="add_links">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label>Bucket: 
      <select name="bucket">
        <option value="read">czytanie</option>
        <option value="watch">oglądanie</option>
        <option value="listen">słuchanie</option>
        <option value="absurd">absurd</option>
      </select>
    </label>
    <div style="margin:8px 0"></div>
    <textarea name="bulk" rows="6" placeholder="Jeden URL w wierszu" required></textarea>
    <div style="margin:8px 0"></div>
    <button>Dodaj</button>
  </form>
</section>
<section>
  <h3>Lista linków</h3>
  <table>
    <thead><tr><th>ID</th><th>Bucket</th><th>URL</th><th>Status</th><th>Akcje</th></tr></thead>
    <tbody>
    <?php $rows = db()->query('SELECT * FROM random_links ORDER BY created_at DESC LIMIT 1000')->fetchAll();
    foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><span class="badge"><?= e($r['bucket']) ?></span></td>
        <td><a href="<?= e($r['url']) ?>" target="_blank" rel="noopener noreferrer"><?= e($r['url']) ?></a></td>
        <td><?= $r['active']? 'aktywne':'wyłączone' ?></td>
        <td>
          <form method="post" action="admin.php?view=links" style="display:inline">
            <input type="hidden" name="do" value="toggle_link">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <input type="hidden" name="active" value="<?= $r['active']?0:1 ?>">
            <button><?= $r['active']? 'Wyłącz':'Włącz' ?></button>
          </form>
          <form method="post" action="admin.php?view=links" style="display:inline" onsubmit="return confirm('Usunąć link?')">
            <input type="hidden" name="do" value="del_link">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button>Usuń</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php elseif ($view==='entries'): ?>
<section>
  <h2>Wpisy / Wiadomości</h2>
  <table>
    <thead><tr><th>ID</th><th>Treść</th><th>Autor</th><th>Kategoria</th><th>Data</th><th>Status</th><th>Akcje</th></tr></thead>
    <tbody>
    <?php $q = db()->query('SELECT e.*, c.name AS cat FROM entries e LEFT JOIN categories c ON c.id=e.category_id ORDER BY e.created_at DESC LIMIT 500');
    foreach ($q as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e(mb_strimwidth($r['content'],0,140,'…')) ?></td>
        <td><?= e($r['author'] ?? '') ?></td>
        <td><?= e($r['cat'] ?? '') ?></td>
        <td><?= e($r['created_at']) ?></td>
        <td><span class="state <?= $r['approved']?'approved':'pending' ?>"><?= $r['approved']?'zatwierdzony':'oczekuje' ?></span></td>
        <td>
          <?php if(!$r['approved']): ?>
          <form method="post" action="admin.php?view=entries" style="display:inline">
            <input type="hidden" name="do" value="approve_entry">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button>Zatwierdź</button>
          </form>
          <?php else: ?>
          <form method="post" action="admin.php?view=entries" style="display:inline">
            <input type="hidden" name="do" value="hide_entry">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button>Ukryj</button>
          </form>
          <?php endif; ?>
          <form method="post" action="admin.php?view=entries" style="display:inline" onsubmit="return confirm('Usunąć wpis?')">
            <input type="hidden" name="do" value="del_entry">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button>Usuń</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php else: ?>
<section>
  <h2>Kategorie</h2>
  <form method="post" action="admin.php?view=categories" style="margin-bottom:12px">
    <input type="hidden" name="do" value="add_category">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input name="name" placeholder="Nowa kategoria" required>
    <button>Dodaj</button>
  </form>
  <table>
    <thead><tr><th>ID</th><th>Nazwa</th><th>Akcje</th></tr></thead>
    <tbody>
    <?php $cats = db()->query('SELECT * FROM categories ORDER BY name')->fetchAll();
    foreach ($cats as $c): ?>
      <tr>
        <td><?= (int)$c['id'] ?></td>
        <td><?= e($c['name']) ?></td>
        <td>
          <?php if ($c['name']!== 'Wszystkie'): ?>
          <form method="post" action="admin.php?view=categories" onsubmit="return confirm('Usunąć kategorię? Wpisy pozostaną bez kategorii.')">
            <input type="hidden" name="do" value="del_category">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button>Usuń</button>
          </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php endif; ?>
</div>
</body></html>
