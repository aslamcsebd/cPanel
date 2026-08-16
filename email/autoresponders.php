<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $r = $client->call('Email', 'add_auto_responder', [
            'email'    => trim($_POST['email'] ?? ''),
            'domain'   => trim($_POST['domain'] ?? ''),
            'from'     => trim($_POST['from'] ?? ''),
            'subject'  => trim($_POST['subject'] ?? ''),
            'body'     => trim($_POST['body'] ?? ''),
            'interval' => (int)($_POST['interval'] ?? 1),
            'charset'  => 'utf-8',
        ]);
        $r['success'] ? $msg = 'Auto responder created.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete') {
        $r = $client->call('Email', 'delete_auto_responder', [
            'email'  => $_POST['email'] ?? '',
            'domain' => $_POST['domain'] ?? '',
        ]);
        $r['success'] ? $msg = 'Auto responder deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$result     = $client->call('Email', 'list_auto_responders');
$responders = $result['data'] ?? [];

$domainsResult = $client->call('DomainInfo', 'list_domains');
$domains = array_filter(array_merge(
    [$domainsResult['data']['main_domain'] ?? ''],
    $domainsResult['data']['addon_domains'] ?? []
));

$pageTitle  = 'Auto Responders — cPanel Manager';
$activePage = 'email-autoresponders';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Auto Responders</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Email/list_auto_responders</p>
  </div>
  <button onclick="document.getElementById('modal-add').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Add Auto Responder
  </button>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Subject</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Interval</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($responders)): ?>
        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">No auto responders found.</td></tr>
        <?php endif; ?>
        <?php foreach ($responders as $r):
          $email   = ($r['email'] ?? '') . '@' . ($r['domain'] ?? '');
          $subject = $r['subject'] ?? '';
          $interval= ($r['interval'] ?? 1) . 'h';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($email) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($subject) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $interval ?></td>
          <td class="px-5 py-3.5 text-right">
            <form method="POST" class="inline">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="email" value="<?= htmlspecialchars($r['email'] ?? '') ?>" />
              <input type="hidden" name="domain" value="<?= htmlspecialchars($r['domain'] ?? '') ?>" />
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
  <div class="w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Auto Responder</h3>
      <button onclick="document.getElementById('modal-add').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="create" />
      <div class="flex gap-2">
        <div class="flex-1">
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
          <input type="text" name="email" placeholder="username" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
          <select name="domain" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
            <?php foreach ($domains as $d): ?><option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">From Name</label>
        <input type="text" name="from" placeholder="Auto Reply" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
        <input type="text" name="subject" placeholder="Out of Office" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Body</label>
        <textarea name="body" rows="4" class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm outline-none focus:border-blue-400 resize-none"></textarea>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Interval (hours)</label>
        <input type="number" name="interval" value="1" min="1" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal-add').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
