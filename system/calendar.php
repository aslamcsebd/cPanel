<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
$pageTitle = 'Calendar & Contacts — cPanel Manager';
$activePage = 'system-calendar';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Calendar & Contacts</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: CPDAVD, DAV, CCS — CalDAV / CardDAV</p>
  </div>
  <button onclick="document.getElementById('modal-add-user').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition">
    <i data-lucide="plus" class="h-4 w-4"></i> Add User
  </button>
</div>

<!-- Feature Notice -->
<div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 flex items-start gap-3">
  <i data-lucide="info" class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"></i>
  <p class="text-sm text-blue-700 dark:text-blue-400">Calendar (CalDAV) and Contacts (CardDAV) are available when the CPDAVD service is enabled on your cPanel server. Connect using any CalDAV/CardDAV compatible client.</p>
</div>

<!-- Tabs -->
<div class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
  <button onclick="switchTab('users')" id="tab-users" class="px-4 py-2.5 text-sm font-medium text-blue-600 border-b-2 border-blue-600">Users</button>
  <button onclick="switchTab('collections')" id="tab-collections" class="px-4 py-2.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 border-b-2 border-transparent">Collections</button>
  <button onclick="switchTab('delegates')" id="tab-delegates" class="px-4 py-2.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 border-b-2 border-transparent">Delegates</button>
</div>

<!-- Users Tab -->
<div id="panel-users">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700/50">
          <tr>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">User</th>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">CalDAV URL</th>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">CardDAV URL</th>
            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
          $users = [
            ['info@example.com',    'https://bdvip2.bdixnode.com:2080/calendars/info/', 'https://bdvip2.bdixnode.com:2080/contacts/info/'],
            ['admin@example.com',   'https://bdvip2.bdixnode.com:2080/calendars/admin/', 'https://bdvip2.bdixnode.com:2080/contacts/admin/'],
          ];
          foreach ($users as [$user, $cal, $card]):
          ?>
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= $user ?></td>
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-2">
                <span class="text-xs font-mono text-gray-500 dark:text-gray-400 truncate max-w-[200px]"><?= $cal ?></span>
                <button onclick="navigator.clipboard?.writeText('<?= $cal ?>')" class="text-gray-400 hover:text-blue-600 flex-shrink-0"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
              </div>
            </td>
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-2">
                <span class="text-xs font-mono text-gray-500 dark:text-gray-400 truncate max-w-[200px]"><?= $card ?></span>
                <button onclick="navigator.clipboard?.writeText('<?= $card ?>')" class="text-gray-400 hover:text-blue-600 flex-shrink-0"><i data-lucide="copy" class="h-3.5 w-3.5"></i></button>
              </div>
            </td>
            <td class="px-5 py-3.5 text-right">
              <div class="flex items-center justify-end gap-1">
                <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="Change Password"><i data-lucide="key" class="h-4 w-4"></i></button>
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
</div>

<!-- Collections Tab -->
<div id="panel-collections" class="hidden">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700/50">
          <tr>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Collection</th>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Owner</th>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Items</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); foreach ([['Personal Calendar','info@example.com','Calendar','12'],['Work Contacts','admin@example.com','Contacts','48'],['Shared Events','info@example.com','Calendar','5']] as [$col,$owner,$type,$items]): ?>
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= $col ?></td>
            <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $owner ?></td>
            <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $type==='Calendar'?'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400':'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' ?>"><?= $type ?></span></td>
            <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $items ?></td>
          </tr>
          <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Delegates Tab -->
<div id="panel-delegates" class="hidden">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Delegates can access another user's calendar or contacts collections.</p>
    <div class="flex gap-3 mb-4">
      <input type="text" placeholder="Delegate email" class="flex-1 h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      <select class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
        <option>Read</option><option>Read/Write</option>
      </select>
      <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add Delegate</button>
    </div>
    <p class="text-sm text-gray-400 dark:text-gray-500 text-center py-4">No delegates configured.</p>
  </div>
</div>

<!-- Add User Modal -->
<div id="modal-add-user" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add DAV User</h3>
      <button onclick="document.getElementById('modal-add-user').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <div class="p-6 space-y-4">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Account</label>
        <select class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
          <option>info@example.com</option><option>support@example.com</option>
        </select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
        <input type="password" placeholder="DAV password" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
    </div>
    <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
      <button onclick="document.getElementById('modal-add-user').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
      <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create</button>
    </div>
  </div>
</div>

<script>
function switchTab(tab) {
  ['users','collections','delegates'].forEach(t => {
    document.getElementById('panel-' + t).classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('tab-' + t);
    if (t === tab) { btn.classList.add('text-blue-600','border-blue-600'); btn.classList.remove('text-gray-500','border-transparent'); }
    else { btn.classList.remove('text-blue-600','border-blue-600'); btn.classList.add('text-gray-500','border-transparent'); }
  });
}
</script>

<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); include '../includes/layout_end.php'; ?>
