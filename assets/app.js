// ===== THEME TOGGLE =====
const html = document.documentElement;
const themeBtn = document.getElementById('theme');
const saved = localStorage.getItem('theme');
if (saved) html.setAttribute('data-theme', saved);
if (themeBtn) {
  themeBtn.addEventListener('click', () => {
    const t = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', t);
    localStorage.setItem('theme', t);
  });
}

// ===== COUNTDOWN =====
const target = new Date('2112-12-21T21:12:00');
function updateCountdown() {
  const now = new Date();
  let diff = target - now;
  if (diff < 0) diff = 0;
  const days = Math.floor(diff / 86400000);
  const weeks = Math.floor(days / 7);
  let months = (target.getFullYear() - now.getFullYear()) * 12 + (target.getMonth() - now.getMonth());
  if (target.getDate() < now.getDate()) months -= 1;
  const byId = (id) => document.getElementById(id);
  if (byId('c-days')) byId('c-days').textContent = days.toLocaleString('pl-PL');
  if (byId('c-weeks')) byId('c-weeks').textContent = weeks.toLocaleString('pl-PL');
  if (byId('c-months')) byId('c-months').textContent = Math.max(0, months).toLocaleString('pl-PL');
}
updateCountdown();
setInterval(updateCountdown, 60000);

// ===== SUBTLE CARD PULSE =====
setInterval(() => {
  document.querySelectorAll('.card').forEach((el) => {
    el.style.transform = 'translateY(-2px)';
    setTimeout(() => { el.style.transform = ''; }, 220);
  });
}, 15000);

// ===== TABS (AJAX ENTRIES) =====
const entries = document.getElementById('entries');
const tabs = document.querySelectorAll('.tab');
function setActiveTab(name) {
  tabs.forEach((t) => t.classList.toggle('active', t.dataset.cat === name));
  fetch(`?ajax=entries&category=${encodeURIComponent(name)}`)
    .then((r) => r.json())
    .then((data) => {
      entries.innerHTML =
        data.map((it) => `<div class="entry">
          <div>${escapeHtml(it.content)}</div>
          <small>${it.author ? escapeHtml(it.author) + ' • ' : ''}${new Date(it.created_at).toLocaleString('pl-PL')}${it.category ? ' • ' + escapeHtml(it.category) : ''}</small>
        </div>`).join('') || '<div class="entry">Brak wpisów.</div>';
    })
    .catch(() => {
      entries.innerHTML = '<div class="entry">Błąd wczytywania wpisów.</div>';
    });
}
function escapeHtml(s) {
  return s ? s.replace(/[&<>"]/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c])) : '';
}
if (tabs.length) {
  setActiveTab('Wszystkie');
  tabs.forEach((t) => t.addEventListener('click', () => setActiveTab(t.dataset.cat)));
}

// ===== PREMIUM TOAST AFTER SUBMISSION (CENTERED) =====
(function showSubmissionToastIfNeeded(){
  const qp = new URLSearchParams(window.location.search);
  if (qp.get('posted') === '1') {
    try { history.replaceState(null, '', window.location.pathname); } catch (_) {}

    const style = document.createElement('style');
    style.textContent = `
      @keyframes toastIn { from { opacity: 0; transform: translate(-50%, -56%); } to { opacity: 1; transform: translate(-50%, -50%); } }
      @keyframes toastOut { to { opacity: 0; transform: translate(-50%, -44%); } }
      .toast2112 {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        max-width: min(92vw, 560px);
        padding: 16px 18px;
        border-radius: 16px;
        background: rgba(0,0,0,0.82);
        color: #fff;
        font-family: -apple-system,BlinkMacSystemFont,"SF Pro Display","Inter",Segoe UI,Roboto,Ubuntu,system-ui,Arial,sans-serif;
        font-size: 15px;
        line-height: 1.4;
        box-shadow: 0 18px 40px rgba(0,0,0,.35), inset 0 1px 0 rgba(255,255,255,.06);
        backdrop-filter: blur(10px);
        letter-spacing: .2px;
        text-align: center;
        white-space: pre-line;
        padding-inline: 22px;
        animation: toastIn .28s ease both;
        z-index: 9999;
      }
      [data-theme="dark"] .toast2112 {
        background: rgba(15,15,15,0.9);
        box-shadow: 0 18px 50px rgba(0,0,0,.6), inset 0 1px 0 rgba(255,255,255,.08);
      }
      .toast2112.hide { animation: toastOut .3s ease forwards; }
    `;
    document.head.appendChild(style);

    const toast = document.createElement('div');
    toast.className = 'toast2112';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.textContent = 'Twoja wiadomość wyruszyła w drogę do roku 2112.\nTeraz przechodzi weryfikację w Komisji.';
    document.body.appendChild(toast);

    setTimeout(() => toast.classList.add('hide'), 2000);
    setTimeout(() => toast.remove(), 2300);
  }
})();
