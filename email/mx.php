<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

$domainsResult = $client->call('DomainInfo', 'list_domains');
$domains = array_filter(array_merge(
    [$domainsResult['data']['main_domain'] ?? ''],
    $domainsResult['data']['addon_domains'] ?? []
));
$selectedDomain = $_GET['domain'] ?? (reset($domains) ?: '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'set_mx') {
        $r = $client->call('Email', 'set_mx', [
            'domain'     => $_POST['domain'] ?? '',
            'mxcheck'    => $_POST['mxcheck'] ?? 'auto',
        ]);
        $r['success'] ? $msg = 'MX routing updated.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'add_mx') {
        $r = $client->call('Email', 'add_mx', [
            'domain'     => $_POST['domain'] ?? '',
            'exchanger'  => trim($_POST['exchanger'] ?? ''),
            'priority'   => (int)($_POST['priority'] ?? 10),
        ]);
        $r['success'] ? $msg = 'MX record added.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete_mx') {
        $r = $client->call('Email', 'delete_mx', [
            'domain'    => $_POST['domain'] ?? '',
            'exchanger' => $_POST['exchanger'] ?? '',
        ]);
        $r['success'] ? $msg = 'MX record deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    }
    $selectedDomain = $_POST['domain'] ?? $selectedDomain;
}

$mxResult = $client->call('Email', 'list_mxs', ['domain' => $selectedDomain]);
$mxData   = $mxResult['data'] ?? [];
$mxs      = $mxData['mxs'] ?? [];
$mxcheck  = $mxData['mxcheck'] ?? 'auto';

$pageTitle  = 'MX Records — cPanel Manager';
$activePage = 'email-mx';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">MX Records</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Email/list_mxs</p>
  </div>
  <button onclick="document.getElementById('modal-add').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Add MX Record
  </button>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Domain selector + routing -->
<div class="flex flex-wrap items-center gap-4">
  <form method="GET" class="flex items-center gap-2">
    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Domain:</label>
    <select name="domain" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
      <?php foreach ($domains as $d): ?>
      <option value="<?= htmlspecialchars($d) ?>" <?= $d === $selectedDomain ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
  <form method="POST" class="flex items-center gap-2">
    <input type="hidden" name="action" value="set_mx" />
    <input type="hidden" name="domain" value="<?= htmlspecialchars($selectedDomain) ?>" />
    <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Email Routing:</label>
    <select name="mxcheck" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
      <option value="auto" <?= $mxcheck === 'auto' ? 'selected' : '' ?>>Automatically Detect</option>
      <option value="local" <?= $mxcheck === 'local' ? 'selected' : '' ?>>Local Mail Exchanger</option>
      <option value="secondary" <?= $mxcheck === 'secondary' ? 'selected' : '' ?>>Backup Mail Exchanger</option>
      <option value="remote" <?= $mxcheck === 'remote' ? 'selected' : '' ?>>Remote Mail Exchanger</option>
    </select>
    <button type="submit" class="rounded-lg bg-blue-600 px-3 py-2 text-sm font-medium text-white hover:bg-blue-700">Save</button>
  </form>
</div>

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Priority</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Exchanger</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($mxs)): ?>
        <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400">No MX records found.</td></tr>
        <?php endif; ?>
        <?php foreach ($mxs as $mx): ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-semibold text-gray-900 dark:text-gray-100"><?= htmlspecialchars($mx['priority'] ?? '') ?></td>
          <td class="px-5 py-3.5 text-sm font-mono text-gray-700 dark:text-gray-300"><?= htmlspecialchars($mx['exchanger'] ?? '') ?></td>
          <td class="px-5 py-3.5 text-right">
            <form method="POST" class="inline">
              <input type="hidden" name="action" value="delete_mx" />
              <input type="hidden" name="domain" value="<?= htmlspecialchars($selectedDomain) ?>" />
              <input type="hidden" name="exchanger" value="<?= htmlspecialchars($mx['exchanger'] ?? '') ?>" />
              <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div id="modal-add" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add MX Record</h3>
      <button onclick="document.getElementById('modal-add').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="add_mx" />
      <input type="hidden" name="domain" value="<?= htmlspecialchars($selectedDomain) ?>" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Priority</label>
        <input type="number" name="priority" value="10" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Mail Exchanger</label>
        <input type="text" name="exchanger" placeholder="mail.example.com" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal-add').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
