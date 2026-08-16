<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
$pageTitle = 'WordPress — cPanel Manager';
$activePage = 'system-wordpress';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">WordPress Manager</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: WordPressManager/list_installations</p>
  </div>
  <button onclick="document.getElementById('modal-install-wp').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition">
    <i data-lucide="plus" class="h-4 w-4"></i> Install WordPress
  </button>
</div>

<!-- Stats -->
<div class="grid gap-4 sm:grid-cols-3">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50"><i data-lucide="layout-dashboard" class="h-5 w-5 text-blue-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Installations</p><p class="text-xl font-semibold text-gray-900 dark:text-white">3</p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50"><i data-lucide="check-circle" class="h-5 w-5 text-green-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Up to Date</p><p class="text-xl font-semibold text-gray-900 dark:text-white">2</p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50"><i data-lucide="alert-triangle" class="h-5 w-5 text-amber-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Update Available</p><p class="text-xl font-semibold text-gray-900 dark:text-white">1</p></div>
  </div>
</div>

<!-- Installations Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Site</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Path</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Version</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Last Backup</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
        $installs = [
          ['example.com',      '/public_html',      '6.5.4', 'Up to Date',       '2025-07-14'],
          ['shop.example.com', '/public_html/shop',  '6.5.4', 'Up to Date',       '2025-07-13'],
          ['blog.example.com', '/public_html/blog',  '6.4.2', 'Update Available', '2025-07-10'],
        ];
        foreach ($installs as [$site, $path, $ver, $status, $backup]):
          $badge = $status === 'Up to Date'
            ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
            : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5">
            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-600 font-bold text-xs">W</div>
              <span class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= $site ?></span>
            </div>
          </td>
          <td class="px-5 py-3.5 text-sm font-mono text-gray-500 dark:text-gray-400"><?= $path ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300"><?= $ver ?></td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $badge ?>"><?= $status ?></span></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $backup ?></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); if ($status === 'Update Available'): ?>
              <button class="inline-flex items-center gap-1.5 h-8 rounded-lg bg-amber-500 px-3 text-xs font-medium text-white hover:bg-amber-600">Update</button>
              <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); endif; ?>
              <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="Backup"><i data-lucide="archive" class="h-4 w-4"></i></button>
              <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="Settings"><i data-lucide="settings" class="h-4 w-4"></i></button>
              <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50" title="Remove"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
            </div>
          </td>
        </tr>
        <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Install WordPress Modal -->
<div id="modal-install-wp" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Install WordPress</h3>
      <button onclick="document.getElementById('modal-install-wp').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <div class="p-6 space-y-4">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
        <select class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
          <option>example.com</option><option>shop.example.com</option><option>dev.example.com</option>
        </select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Install Path</label>
        <input type="text" placeholder="/public_html" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Admin Username</label>
        <input type="text" placeholder="admin" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Admin Password</label>
        <input type="password" placeholder="Strong password" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Admin Email</label>
        <input type="email" placeholder="admin@example.com" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
    </div>
    <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
      <button onclick="document.getElementById('modal-install-wp').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
      <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Install</button>
    </div>
  </div>
</div>

<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); include '../includes/layout_end.php'; ?>
