<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
$pageTitle = 'Email Filters — cPanel Manager';
$activePage = 'email-filters';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Email Filters</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Email/list_filters, Email/store_filter</p>
  </div>
  <button onclick="document.getElementById('modal-add-filter').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition">
    <i data-lucide="plus" class="h-4 w-4"></i> Add Filter
  </button>
</div>

<!-- Account selector -->
<div class="flex items-center gap-3">
  <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Account:</label>
  <select class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
    <option>info@example.com</option>
    <option>support@example.com</option>
    <option>admin@example.com</option>
  </select>
</div>

<!-- Filters Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Filter Name</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Condition</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Action</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Priority</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
        $filters = [
          ['Block Spam','Subject contains [SPAM]','Delete','1'],
          ['Newsletter Archive','From contains newsletter','Move to folder: newsletters','2'],
          ['Priority Support','Subject contains URGENT','Forward to admin@example.com','3'],
          ['Auto-tag AWS','From contains aws.amazon.com','Move to folder: aws','4'],
        ];
        foreach ($filters as [$name, $condition, $action, $priority]):
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= $name ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300"><?= $condition ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $action ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $priority ?></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="Edit"><i data-lucide="pencil" class="h-4 w-4"></i></button>
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

<!-- Add Filter Modal -->
<div id="modal-add-filter" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Email Filter</h3>
      <button onclick="document.getElementById('modal-add-filter').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <div class="p-6 space-y-4">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Filter Name</label>
        <input type="text" placeholder="e.g. Block Spam" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Condition</label>
        <div class="grid grid-cols-3 gap-2">
          <select class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
            <option>Subject</option><option>From</option><option>To</option><option>Body</option><option>Any Header</option>
          </select>
          <select class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
            <option>contains</option><option>does not contain</option><option>begins with</option><option>ends with</option><option>matches regex</option>
          </select>
          <input type="text" placeholder="value" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
        </div>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Action</label>
        <select id="filter-action" onchange="toggleActionTarget()" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
          <option value="delete">Delete</option>
          <option value="folder">Move to Folder</option>
          <option value="forward">Forward to</option>
          <option value="stop">Stop Processing</option>
        </select>
      </div>
      <div id="action-target">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Target</label>
        <input type="text" placeholder="Folder name or email address" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
    </div>
    <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
      <button onclick="document.getElementById('modal-add-filter').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
      <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create Filter</button>
    </div>
  </div>
</div>

<script>
function toggleActionTarget() {
  const action = document.getElementById('filter-action').value;
  document.getElementById('action-target').classList.toggle('hidden', action === 'delete' || action === 'stop');
}
</script>

<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); include '../includes/layout_end.php'; ?>
