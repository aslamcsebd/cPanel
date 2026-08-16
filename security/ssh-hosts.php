<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
$pageTitle = 'SSH Hosts — cPanel Manager';
$activePage = 'security-ssh';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Known SSH Hosts</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: SSH/list_keys, SSH/import_key</p>
  </div>
  <button onclick="document.getElementById('modal-add-key').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition">
    <i data-lucide="plus" class="h-4 w-4"></i> Add SSH Key
  </button>
</div>

<!-- Warning -->
<div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-4 flex items-start gap-3">
  <i data-lucide="alert-triangle" class="h-5 w-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5"></i>
  <p class="text-sm text-amber-700 dark:text-amber-400">Only add SSH keys from trusted sources. Removing an active key will immediately revoke SSH access for that key.</p>
</div>

<!-- SSH Keys Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Key Name</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Fingerprint</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
        $keys = [
          ['id_rsa',        'RSA 4096', 'SHA256:abc123...def456', 'Authorized'],
          ['deploy_key',    'ED25519',  'SHA256:xyz789...uvw012', 'Authorized'],
          ['backup_server', 'RSA 2048', 'SHA256:mno345...pqr678', 'Not Authorized'],
        ];
        foreach ($keys as [$name, $type, $fp, $status]):
          $badge = $status === 'Authorized'
            ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
            : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5">
            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700"><i data-lucide="key" class="h-4 w-4 text-gray-500 dark:text-gray-400"></i></div>
              <span class="text-sm font-medium font-mono text-gray-900 dark:text-gray-100"><?= $name ?></span>
            </div>
          </td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $type ?></td>
          <td class="px-5 py-3.5 text-sm font-mono text-gray-500 dark:text-gray-400"><?= $fp ?></td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $badge ?>"><?= $status ?></span></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); if ($status !== 'Authorized'): ?>
              <button class="inline-flex items-center gap-1.5 h-8 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white hover:bg-blue-700">Authorize</button>
              <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); else: ?>
              <button class="inline-flex items-center gap-1.5 h-8 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-medium text-amber-700 hover:bg-amber-100">Deauthorize</button>
              <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); endif; ?>
              <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="View"><i data-lucide="eye" class="h-4 w-4"></i></button>
              <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
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

<!-- Add SSH Key Modal -->
<div id="modal-add-key" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add SSH Key</h3>
      <button onclick="document.getElementById('modal-add-key').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <div class="p-6 space-y-4">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Key Name</label>
        <input type="text" placeholder="e.g. my_laptop" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Public Key</label>
        <textarea rows="5" placeholder="ssh-rsa AAAA... or ssh-ed25519 AAAA..." class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-mono outline-none focus:border-blue-400 resize-none"></textarea>
      </div>
      <div class="flex items-center gap-2">
        <input type="checkbox" id="auto-authorize" class="h-4 w-4 rounded border-gray-300" />
        <label for="auto-authorize" class="text-sm text-gray-600 dark:text-gray-400">Authorize immediately after import</label>
      </div>
    </div>
    <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
      <button onclick="document.getElementById('modal-add-key').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
      <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Import Key</button>
    </div>
  </div>
</div>

<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); include '../includes/layout_end.php'; ?>
