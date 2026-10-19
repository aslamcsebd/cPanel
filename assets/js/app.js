// Sidebar toggle (mobile)
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const o = document.getElementById('sidebar-overlay');
  s.classList.toggle('-translate-x-full');
  o.classList.toggle('hidden');
}

// Sidebar collapse (desktop)
function toggleSidebarCollapse() {
  const sidebar = document.getElementById('sidebar');
  const main = document.getElementById('main-content');
  const btn = document.getElementById('sidebar-collapse-btn');
  const collapsed = sidebar.classList.toggle('sidebar-collapsed');

  sidebar.classList.toggle('w-64', !collapsed);
  sidebar.classList.toggle('w-16', collapsed);
  main.classList.toggle('lg:pl-64', !collapsed);
  main.classList.toggle('lg:pl-16', collapsed);

  const icon = btn.querySelector('i');
  icon.setAttribute('data-lucide', collapsed ? 'chevron-right' : 'chevron-left');
  if (window.lucide) lucide.createIcons();

  fetch(APP_BASE + '/api/save_sidebar.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'collapsed=' + collapsed
  });
}

// Close dropdowns on outside click
document.addEventListener('click', function(e) {
  document.querySelectorAll('[data-dropdown]').forEach(function(dd) {
    if (!dd.contains(e.target)) {
      const m = dd.querySelector('[data-dropdown-menu]');
      if (m) m.classList.add('hidden');
    }
  });
});

// Theme
function applyTheme(theme) {
  document.documentElement.classList.toggle('dark', theme === 'dark');
  updateThemeIcon(theme);
  // Save to config.json via AJAX
  fetch(APP_BASE + '/api/save_theme.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: 'theme=' + theme
  });
}

function updateThemeIcon(theme) {
  const btn = document.getElementById('theme-toggle');
  if (!btn) return;

  // Remove old svg/i inside button
  btn.innerHTML = '';

  // Create fresh i tag with correct icon
  const i = document.createElement('i');
  i.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon');
  i.className = 'h-5 w-5';
  i.style.color = theme === 'dark' ? '#facc15' : '#6366f1';
  btn.appendChild(i);
  btn.title = theme === 'dark' ? 'Switch to Light' : 'Switch to Dark';

  // Button bg — opposite of current theme
  btn.style.backgroundColor = theme === 'dark' ? '#1f2937' : '#f3f4f6';
  btn.style.borderRadius = '0.5rem';

  if (window.lucide) {
    lucide.createIcons({ nameAttr: 'data-lucide', nodes: [i] });
    const svg = btn.querySelector('svg');
    if (svg) svg.style.color = theme === 'dark' ? '#facc15' : '#6366f1';
  }
}

function toggleTheme(event) {
  const next = document.documentElement.classList.contains('dark') ? 'light' : 'dark';
  if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches && 'startViewTransition' in document) {
    // Freeze all transitions so every element renders instantly in new theme
    document.documentElement.style.setProperty('--transition-freeze', 'none');
    const els = document.querySelectorAll('*');
    els.forEach(el => el.style.transition = 'none');
    document.startViewTransition(() => {
      document.documentElement.classList.toggle('dark', next === 'dark');
      updateThemeIcon(next);
      fetch(APP_BASE + '/api/save_theme.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'theme=' + next
      });
    }).finished.then(() => {
      // Restore transitions after sweep completes
      els.forEach(el => el.style.transition = '');
    });
  } else {
    applyTheme(next);
  }
}

document.addEventListener('DOMContentLoaded', function() {
  const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
  updateThemeIcon(theme);

  // Restore sidebar scroll position
  var nav = document.querySelector('#sidebar nav');
  if (nav) {
    var saved = sessionStorage.getItem('sidebarScroll');
    if (saved) nav.scrollTop = parseInt(saved, 10);

    // Save scroll position before any navigation
    nav.addEventListener('scroll', function() {
      sessionStorage.setItem('sidebarScroll', nav.scrollTop);
    });
  }
});
