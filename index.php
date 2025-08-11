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
<html lang="pl" data-theme="dark">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Kapsuła Czasu 2112</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Roboto+Condensed:wght@300;400;700&display=swap" rel="stylesheet">
<style>
  :root {
    --radius: 16px;
    --gap: 20px;
    /* Light Theme */
    --light-bg: #ffffff;
    --light-card-bg: #f9fafb;
    --light-border: #f3f4f6;
    --light-text-primary: #111827;
    --light-text-secondary: #6b7280;
    --light-icon-color: #374151;
    /* Dark Theme */
    --dark-bg: #030712;
    --dark-card-bg: #111827;
    --dark-border: #1f2937;
    --dark-text-primary: #f9fafb;
    --dark-text-secondary: #9ca3af;
    --dark-icon-color: #d1d5db;
  }
  html { scroll-behavior: smooth; }
  body {
    margin: 0;
    font-family: 'Roboto Condensed', sans-serif;
    background: var(--dark-bg);
    color: var(--dark-text-primary);
    transition: background 0.3s, color 0.3s;
  }
  [data-theme="light"] body {
    background: var(--light-bg);
    color: var(--light-text-primary);
  }
  .wrap { max-width: 960px; margin: 0 auto; padding: 48px 24px; }
  
  /* Header & Brand */
  header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 56px;
  }
  .brand { 
    text-align: center;
    width: 100%;
    max-width: 720px;
    margin-left: auto;
    margin-right: auto;
  }
  .brand h1 {
    font-size: clamp(36px, 5vw, 52px);
    font-weight: 400; 
    letter-spacing: -0.01em;
    margin: 0;
  }
  .brand p {
    font-size: 18px;
    color: var(--dark-text-secondary);
    margin: 16px 0 0;
    font-weight: 300;
    line-height: 1.6;
  }
  /* Dodatkowy styl dla akapitów, aby miały odstęp */
  .brand p + p {
      margin-top: 1em;
  }
  [data-theme="light"] .brand p { color: var(--light-text-secondary); }

  /* Theme Toggle */
  .theme-toggle {
    border: 1px solid var(--dark-border);
    background: var(--dark-card-bg);
    border-radius: 999px;
    padding: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    position: absolute;
    top: 48px;
    right: 24px;
  }
  [data-theme="light"] .theme-toggle {
    background: var(--light-card-bg);
    border-color: var(--light-border);
  }
  .theme-toggle .icon {
    width: 24px;
    height: 24px;
    color: var(--dark-icon-color);
  }
  [data-theme="light"] .theme-toggle .icon { color: var(--light-icon-color); }
  .theme-toggle .icon-sun { display: none; }
  .theme-toggle .icon-moon { display: block; }
  [data-theme="light"] .theme-toggle .icon-sun { display: block; }
  [data-theme="light"] .theme-toggle .icon-moon { display: none; }

  /* Time Remaining Card */
  .time-card {
    background: var(--dark-card-bg);
    border: 1px solid var(--dark-border);
    border-radius: var(--radius);
    padding: 24px 32px;
    text-align: left;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    margin-bottom: 32px;
  }
  [data-theme="light"] .time-card {
    background: var(--light-card-bg);
    border-color: var(--light-border);
  }
  .time-card .title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 400;
    font-size: 16px;
    color: var(--dark-text-secondary);
    margin-bottom: 24px;
  }
  .time-card .title svg { width: 20px; height: 20px; }
  [data-theme="light"] .time-card .title { color: var(--light-text-secondary); }
  .time-card .countdown {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--gap);
  }
  .time-card .value {
    font-size: clamp(36px, 6vw, 52px);
    font-weight: 700;
    letter-spacing: 0.01em;
    line-height: 1;
  }
  .time-card .label {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--dark-text-secondary);
    margin-top: 8px;
    font-weight: 400;
  }
  [data-theme="light"] .time-card .label { color: var(--light-text-secondary); }

  /* Action Buttons */
  .actions {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: var(--gap);
  }
  .action-btn {
    display: flex;
    align-items: center;
    gap: 16px;
    text-decoration: none;
    padding: 20px;
    border-radius: var(--radius);
    background: var(--dark-card-bg);
    border: 1px solid var(--dark-border);
    color: var(--dark-text-primary);
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s, box-shadow 0.2s, border-color 0.2s;
  }
  .action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
    border-color: #374151;
  }
  [data-theme="light"] .action-btn {
    background: var(--light-card-bg);
    border-color: #e5e7eb;
    color: var(--light-text-primary);
  }
  [data-theme="light"] .action-btn:hover { border-color: #d1d5db; }
  .action-btn .icon { width: 28px; height: 28px; color: var(--dark-icon-color); }
  [data-theme="light"] .action-btn .icon { color: var(--light-icon-color); }
  .action-btn .text-content { flex-grow: 1; }
  .action-btn .text { font-weight: 700; font-size: 16px; }
  .action-btn .external-icon { width: 14px; height: 14px; opacity: 0.6; }
  
  /* Wall of Messages Section */
  .panel { margin-top: 64px; }
  .panel h2 { 
    margin-top: 0; 
    font-size: 24px; 
    font-weight: 400; 
    text-align: center;
  }
  
  /* Form Elements */
  textarea, input, select, .btn {
    width: 100%;
    border-radius: 8px;
    border: 1px solid #374151;
    padding: 12px;
    background: #1f2937;
    font-family: inherit;
    font-size: 16px;
    box-sizing: border-box;
    color: var(--dark-text-primary);
  }
  [data-theme="light"] textarea, [data-theme="light"] input, [data-theme="light"] select {
    background: #f9fafb;
    border-color: #d1d5db;
    color: var(--light-text-primary);
  }
  form .row { display: grid; gap: 12px; margin: 24px 0 18px; }
  .btn {
    cursor: pointer;
    background: #4f46e5; color: white;
    font-weight: 700; border: none;
    transition: background 0.2s;
  }
  .btn:hover { background: #4338ca; }
  
  /* Tabs & Entries */
  .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px; }
  .tab { padding: 8px 14px; border-radius: 999px; cursor: pointer; font-weight: 400; opacity: 0.8; border: 1px solid transparent; }
  .tab:hover { opacity: 1; }
  .tab.active { opacity: 1; background: rgba(99, 102, 241, 0.2); color: #c7d2fe; }
  [data-theme="light"] .tab.active { background: rgba(79, 70, 229, 0.1); color: #4f46e5; }
  
  .list { display: grid; gap: 12px; }
  .entry { padding: 16px; border-radius: 8px; background: var(--dark-card-bg); border: 1px solid var(--dark-border); font-weight: 300; }
  [data-theme="light"] .entry { background: var(--light-bg); border: 1px solid #e5e7eb; }
  .entry small { display: block; margin-top: 8px; opacity: 0.7; font-size: 14px; font-weight: 400;}
  
  footer { opacity: 0.6; margin-top: 48px; text-align: center; font-weight: 300; }

  @media (max-width: 768px) {
    .actions, .time-card .countdown {
      grid-template-columns: repeat(2, 1fr);
    }
    .brand {
        text-align: center;
    }
    .theme-toggle {
    }
  }
  @media (max-width: 480px) {
    .actions, .time-card .countdown {
      gap: 12px;
    }
    .action-btn {
        flex-direction: column;
        gap: 8px;
        text-align: center;
    }
    .action-btn .external-icon {
        display: none;
    }
  }
</style>
</head>
<body>
<div class="wrap">
  <header>
    <div class="brand">
        <h1>Ocalamy to, co ma wartość</h1>
        <p>Internet zalewają miliardy treści, które jutro przepadną bez śladu. Komisja poszukuje tych, które powinny przetrwać do 2112 roku – słów, obrazów, dźwięków i pomysłów wartych ocalenia.</p>
        <p>Co roku, 21 grudnia, zakopujemy cyfrową kapsułę czasu – zaszyfrowane dyski i pendrive’y z zebranymi wiadomościami trafiają w ziemię w sekretnych miejscach w Polsce i na świecie. Każdy może dołożyć do niej swój fragment historii – wystarczy podzielić się tym, co warto zachować dla przyszłych pokoleń.</p>
        <p>Komisja czuwa nad bezpieczeństwem i integralnością archiwum. Szukamy również ludzi, którzy chcieliby udostępnić kawałek swojej ziemi, by stała się strażnikiem wspomnień teraźniejszości i przeszłości.</p>
    </div>
    <button id="theme" class="theme-toggle" aria-label="Przełącz motyw">
      <svg class="icon icon-sun" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>
      <svg class="icon icon-moon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.718 9.718 0 0118 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 003 11.25c0 5.385 4.365 9.75 9.75 9.75 2.572 0 4.92-.99 6.752-2.648z" /></svg>
    </button>
  </header>

  <main>
    <section class="time-card">
      <div class="title">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
        <span>Pozostało czasu</span>
      </div>
      <div id="countdown" class="countdown">
        <div><div class="value" id="c-years">—</div><div class="label">Lat</div></div>
        <div><div class="value" id="c-months">—</div><div class="label">Miesięcy</div></div>
        <div><div class="value" id="c-weeks">—</div><div class="label">Tygodni</div></div>
        <div><div class="value" id="c-days">—</div><div class="label">Dni</div></div>
      </div>
    </section>

    <section class="actions">
      <a class="action-btn" href="?go=read" target="_blank" rel="noopener noreferrer">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18c-2.305 0-4.408.867-6 2.292m0-14.25v14.25" /></svg>
        <div class="text-content"><div class="text">Read</div></div>
        <svg class="external-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-4.5 0V6.375c0-.621.504-1.125 1.125-1.125h4.25c.621 0 1.125.504 1.125 1.125V10.5m-4.5 0h4.5" /></svg>
      </a>
      <a class="action-btn" href="?go=watch" target="_blank" rel="noopener noreferrer">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639l4.433-7.48a1.012 1.012 0 011.636 0l4.434 7.48a1.012 1.012 0 010 .638l-4.433 7.48a1.012 1.012 0 01-1.636 0l-4.434-7.48z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
        <div class="text-content"><div class="text">Watch</div></div>
        <svg class="external-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-4.5 0V6.375c0-.621.504-1.125 1.125-1.125h4.25c.621 0 1.125.504 1.125 1.125V10.5m-4.5 0h4.5" /></svg>
      </a>
      <a class="action-btn" href="?go=listen" target="_blank" rel="noopener noreferrer">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.114 5.636a9 9 0 010 12.728M16.463 8.288a5.25 5.25 0 010 7.424M6.75 8.25l4.72-4.72a.75.75 0 011.28.53v15.88a.75.75 0 01-1.28.53l-4.72-4.72H4.51c-.88 0-1.704-.507-1.938-1.354A9.01 9.01 0 012.25 12c0-.83.112-1.633.322-2.396C2.806 8.756 3.63 8.25 4.51 8.25H6.75z" /></svg>
        <div class="text-content"><div class="text">Listen</div></div>
        <svg class="external-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-4.5 0V6.375c0-.621.504-1.125 1.125-1.125h4.25c.621 0 1.125.504 1.125 1.125V10.5m-4.5 0h4.5" /></svg>
      </a>
      <a class="action-btn" href="?go=absurd" target="_blank" rel="noopener noreferrer">
        <svg class="icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" /></svg>
        <div class="text-content"><div class="text">Absurd</div></div>
        <svg class="external-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-4.5 0V6.375c0-.621.504-1.125 1.125-1.125h4.25c.621 0 1.125.504 1.125 1.125V10.5m-4.5 0h4.5" /></svg>
      </a>
    </section>

    <section id="wall" class="panel">
      <h2>Wyślij wiadomość w przyszłość</h2>
      <form method="post">
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
      <small style="opacity:.7; font-size: 14px;">Nowe wpisy trafiają do moderacji i pojawiają się po akceptacji.</small>
    </section>
  </main>
  
  <footer>© <?= date('Y') ?> Kapsuła Czasu 2112</footer>
</div>
<script src="/assets/app.js"></script>
</body>
</html>
