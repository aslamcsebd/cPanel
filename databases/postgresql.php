<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
$pageTitle = 'PostgreSQL — cPanel Manager';
$activePage = 'databases-pg';
include '../includes/layout.php';
?>
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">PostgreSQL</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Postgresql/list_databases</p>
  </div>
  <button class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition">
    <i data-lucide="plus" class="h-4 w-4"></i> Add New
  </button>
</div>
<div class="rounded-xl border border-dashed border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 p-16 text-center shadow-sm">
  <div class="flex h-14 w-14 items-center justify-center rounded-xl bg-blue-50 dark:bg-blue-900/20 mx-auto mb-4">
    <i data-lucide="database" class="h-7 w-7 text-blue-600 dark:text-blue-400"></i>
  </div>
  <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-2">PostgreSQL</h2>
  <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mx-auto mb-6">UI scaffold ready. Connect backend to load live cPanel data.</p>
  <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-4 py-2">
    <i data-lucide="terminal" class="h-4 w-4 text-gray-400"></i>
    <span class="text-xs font-mono text-gray-500 dark:text-gray-400">Postgresql/list_databases</span>
  </div>
</div>
<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); include '../includes/layout_end.php'; ?>
