// Sidebar toggle
function toggleSidebar() {
  const s = document.getElementById('sidebar');
  const o = document.getElementById('sidebar-overlay');
  s.classList.toggle('-translate-x-full');
  o.classList.toggle('hidden');
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
  const icon = btn.querySelector('i');
  if (!icon) return;
  icon.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon');
  if (window.lucide) lucide.createIcons();
}

function toggleTheme() {
  const current = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
  applyTheme(current === 'dark' ? 'light' : 'dark');
}

document.addEventListener('DOMContentLoaded', function() {
  const theme = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
  updateThemeIcon(theme);
});
