<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
$pageTitle = 'API Tokens — cPanel Manager';
$activePage = 'api-tokens';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">API Tokens</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Tokens/list, Tokens/create_full_access, Tokens/revoke</p>
  </div>
  <button onclick="document.getElementById('modal-create-token').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition">
    <i data-lucide="plus" class="h-4 w-4"></i> Create Token
  </button>
</div>

<!-- Security Notice -->
<div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-4 flex items-start gap-3">
  <i data-lucide="alert-triangle" class="h-5 w-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5"></i>
  <div>
    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">Security Notice</p>
    <p class="text-sm text-amber-700 dark:text-amber-400 mt-0.5">API tokens are only shown once at creation. Never share tokens or store them in code. Revoke unused tokens immediately.</p>
  </div>
</div>

<!-- Tokens Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Token Name</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Created</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Last Used</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
        $tokens = [
          ['TBEOIAGB73P55R9K...','2025-07-01','2025-07-15','Active'],
          ['deploy-token-prod','2025-06-15','2025-07-14','Active'],
          ['backup-script','2025-05-01','2025-06-30','Active'],
        ];
        foreach ($tokens as [$name, $created, $lastUsed, $status]):
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5">
            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20"><i data-lucide="key" class="h-4 w-4 text-blue-600 dark:text-blue-400"></i></div>
              <span class="text-sm font-medium font-mono text-gray-900 dark:text-gray-100"><?= $name ?></span>
            </div>
          </td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $created ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $lastUsed ?></td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full bg-green-50 dark:bg-green-900/20 px-2.5 py-1 text-xs font-medium text-green-700 dark:text-green-400"><?= $status ?></span></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="Rename"><i data-lucide="pencil" class="h-4 w-4"></i></button>
              <button class="inline-flex items-center gap-1.5 h-8 rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-medium text-red-600 hover:bg-red-100">Revoke</button>
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

<!-- Create Token Modal -->
<div id="modal-create-token" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Create API Token</h3>
      <button onclick="document.getElementById('modal-create-token').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <div class="p-6 space-y-4">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Token Name</label>
        <input type="text" placeholder="e.g. deploy-script" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-3 text-xs text-amber-700 dark:text-amber-400">
        The token will only be shown once after creation. Copy and store it securely.
      </div>
    </div>
    <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
      <button onclick="document.getElementById('modal-create-token').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
      <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create Token</button>
    </div>
  </div>
</div>

<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); include '../includes/layout_end.php'; ?>
