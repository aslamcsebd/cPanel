<?php
// Set $pageTitle and $activePage before including this file
require_once __DIR__ . '/auth.php';
$basePath = $basePath ?? '../';
function navLink($href, $icon, $label, $active) {
  $cls = $active
    ? 'flex items-center gap-3 rounded-lg bg-blue-50 dark:bg-blue-900/30 px-3 py-2 text-sm font-medium text-blue-700 dark:text-blue-400'
    : 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-white';
  echo "<a href=\"{$href}\" class=\"{$cls}\"><i data-lucide=\"{$icon}\" class=\"h-4 w-4 flex-shrink-0\"></i>{$label}</a>";
}
function navSection($label) {
  echo "<p class=\"px-3 pt-4 pb-1 text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500\">{$label}</p>";
}
function renderSidebar(string $basePath, string $activePage) {
  $items = getSidebarItems();
  $renderedSections = [];
  foreach ($items as $item) {
    if (empty($item['enabled'])) continue;
    $section = strtoupper($item['section'] ?? '');
    if ($section !== '' && !in_array($section, $renderedSections)) {
      navSection($section);
      $renderedSections[] = $section;
    }
    $pageKey = $item['id'] ?? '';
    navLink($basePath . $item['href'], $item['icon'], $item['label'], $activePage === $pageKey);
  }
}
$p = $activePage ?? '';
$b = $basePath;
?>
<!doctype html>
<html lang="en" class="">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle ?? 'cPanel Manager') ?></title>
  <script>
    (function(){
      var t = <?= json_encode(readConfig()['app']['theme'] ?? 'light') ?>;
      if(t === 'dark') document.documentElement.classList.add('dark');
    })()
  </script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={darkMode:"class"}</script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 font-sans text-gray-900 dark:text-gray-100">

<div id="sidebar-overlay" class="fixed inset-0 z-20 bg-black/30 hidden lg:hidden" onclick="toggleSidebar()"></div>

<aside id="sidebar" class="fixed inset-y-0 left-0 z-30 w-64 border-r border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 flex flex-col -translate-x-full lg:translate-x-0 transition-transform duration-200">
  <div class="flex h-16 items-center gap-2 border-b border-gray-200 dark:border-gray-700 px-5">
    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-600">
      <i data-lucide="server" class="h-4 w-4 text-white"></i>
    </div>
    <span class="text-base font-semibold text-gray-900 dark:text-white">cPanel Manager</span>
  </div>
  <nav class="flex-1 overflow-y-auto p-3 space-y-0.5">
    <?php renderSidebar($b, $p); ?>
  </nav>
  <div class="border-t border-gray-200 dark:border-gray-700 p-3">
    <div class="flex items-center gap-3 rounded-lg px-3 py-2">
      <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white text-sm font-semibold flex-shrink-0">A</div>
      <div class="flex-1 min-w-0">
        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">Admin</p>
        <p class="text-xs text-gray-500 dark:text-gray-400 truncate">bdvip2.bdixnode.com</p>
      </div>
      <a href="<?= $b ?>logout.php" title="Logout"><i data-lucide="log-out" class="h-4 w-4 text-gray-400 hover:text-red-500"></i></a>
    </div>
  </div>
</aside>

<div class="lg:pl-64 min-h-screen">
  <header class="sticky top-0 z-40 flex h-16 items-center justify-between border-b border-gray-200 dark:border-gray-700 bg-white/95 dark:bg-gray-800/95 px-4 backdrop-blur md:px-6">
    <button onclick="toggleSidebar()" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 lg:hidden">
      <i data-lucide="menu" class="h-5 w-5"></i>
    </button>
    <div class="flex h-10 items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 w-64 hidden md:flex">
      <i data-lucide="search" class="h-4 w-4 text-gray-400"></i>
      <input type="text" placeholder="Search..." class="bg-transparent text-sm text-gray-700 dark:text-gray-200 outline-none placeholder:text-gray-400 w-full" />
    </div>
    <div class="flex items-center gap-2 ml-auto">
      <?php if (isCpanelConfigured()): ?>
        <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-green-50 dark:bg-green-900/20 px-2.5 py-1 text-xs font-medium text-green-700 dark:text-green-400">
          <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Connected
        </span>
        <div class="relative" data-dropdown>
          <button onclick="this.closest('[data-dropdown]').querySelector('[data-dropdown-menu]').classList.toggle('hidden')" class="relative inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
            <i data-lucide="bell" class="h-5 w-5"></i>
          </button>
          <div data-dropdown-menu class="hidden absolute right-0 top-full mt-2 w-72 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg z-50">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700"><p class="text-sm font-semibold text-gray-900 dark:text-white">Notifications</p></div>
            <div class="p-3 space-y-1">
              <p class="text-xs text-gray-400 text-center py-2">All systems operational</p>
            </div>
          </div>
        </div>
      <?php else: ?>
        <span class="hidden sm:inline-flex items-center gap-1.5 rounded-full bg-amber-50 dark:bg-amber-900/20 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-400">
          <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Setup Required
        </span>
      <?php endif; ?>
      <button id="theme-toggle" onclick="toggleTheme()" class="inline-flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-700">
        <i data-lucide="moon" class="h-5 w-5"></i>
      </button>
    </div>
  </header>
  <main class="p-4 md:p-6 space-y-6">
