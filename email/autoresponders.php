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

$perPage = 10;
$totalResponders = count($responders);
$totalPages = max(1, (int)ceil($totalResponders / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$pagedResponders = array_slice($responders, $offset, $perPage);

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
        <?php if (empty($pagedResponders)): ?>
        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">No auto responders found.</td></tr>
        <?php endif; ?>
        <?php foreach ($pagedResponders as $r):
          $rawEmail = $r['email'] ?? '';
          $email = str_contains($rawEmail, '@') ? $rawEmail : ($rawEmail !== '' ? $rawEmail . '@' . ($r['domain'] ?? '') : ($r['domain'] ?? ''));
          $subject = $r['subject'] ?? '';
          $interval= ($r['interval'] ?? 1) . 'h';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($email) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($subject) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $interval ?></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <button onclick="openEditModal('<?= htmlspecialchars($r['email'] ?? '') ?>', '<?= htmlspecialchars($r['domain'] ?? '') ?>', '<?= htmlspecialchars($r['subject'] ?? '') ?>', '<?= htmlspecialchars($r['body'] ?? '') ?>', '<?= htmlspecialchars($r['from'] ?? '') ?>', <?= (int)($r['interval'] ?? 1) ?>)" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="Edit"><i data-lucide="pencil" class="h-4 w-4"></i></button>
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="email" value="<?= htmlspecialchars($r['email'] ?? '') ?>" />
                <input type="hidden" name="domain" value="<?= htmlspecialchars($r['domain'] ?? '') ?>" />
                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4">
  <span class="text-sm text-gray-500 dark:text-gray-400">Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalResponders) ?> of <?= $totalResponders ?> auto responders</span>
  <div class="flex items-center gap-1">
    <?php if ($page > 1): ?>
      <a href="?page=1" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="First"><i data-lucide="chevrons-left" class="h-4 w-4 text-gray-500"></i></a>
      <a href="?page=<?= $page - 1 ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="Previous"><i data-lucide="chevron-left" class="h-4 w-4 text-gray-500"></i></a>
    <?php endif; ?>
    <?php
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($start > 1) echo '<span class="h-8 w-8 flex items-center justify-center text-gray-400">…</span>';
    for ($i = $start; $i <= $end; $i++):
    ?>
      <?php if ($i === $page): ?>
        <span class="h-8 w-8 rounded bg-blue-600 text-white text-sm font-medium flex items-center justify-center"><?= $i ?></span>
      <?php else: ?>
        <a href="?page=<?= $i ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($end < $totalPages) echo '<span class="h-8 w-8 flex items-center justify-center text-gray-400">…</span>'; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?page=<?= $page + 1 ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="Next"><i data-lucide="chevron-right" class="h-4 w-4 text-gray-500"></i></a>
      <a href="?page=<?= $totalPages ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="Last"><i data-lucide="chevrons-right" class="h-4 w-4 text-gray-500"></i></a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Add Modal -->
<div id="modal-add" class="hidden fixed inset-0 z-50 flex items-center justify-center">
  <div class="fixed inset-0 bg-black/70" onclick="closeAddModal()"></div>
  <div class="relative z-50 w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl mx-4">
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-t-xl">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Auto Responder</h3>
      <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" id="form-add" class="p-5 space-y-3" autocomplete="off">
      <input type="hidden" name="action" value="create" />
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
        <div class="flex rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:border-blue-400 focus-within:ring-1 focus-within:ring-blue-400">
          <input type="text" name="email" placeholder="username" class="h-10 flex-1 bg-transparent px-3 text-sm outline-none min-w-0" required />
          <select name="domain" class="h-10 border-l border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-2 text-sm outline-none rounded-r-lg max-w-[180px]">
            <?php foreach ($domains as $d): ?><option value="<?= htmlspecialchars($d) ?>">@<?= htmlspecialchars($d) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">From Name</label>
        <input type="text" name="from" placeholder="Auto Reply" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
        <input type="text" name="subject" placeholder="Out of Office" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Body</label>
        <textarea name="body" rows="3" class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm outline-none focus:border-blue-400 resize-none"></textarea>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Interval (hours)</label>
        <input type="number" name="interval" value="1" min="1" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex justify-between pt-1">
        <button type="button" onclick="closeAddModal()" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modal -->
<div id="modal-edit" class="hidden fixed inset-0 z-50 flex items-center justify-center">
  <div class="fixed inset-0 bg-black/70" onclick="closeEditModal()"></div>
  <div class="relative z-50 w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl mx-4">
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-t-xl">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Edit Auto Responder</h3>
      <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" id="form-edit" class="p-5 space-y-3" autocomplete="off">
      <input type="hidden" name="action" value="delete" id="edit-delete-action" />
      <input type="hidden" name="email" id="edit-email-hidden" />
      <input type="hidden" name="domain" id="edit-domain-hidden" />
      <input type="hidden" name="action" value="create" id="edit-create-action" disabled />
      <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
        <p class="text-xs text-gray-500">Editing</p>
        <p id="edit-email-display" class="text-sm font-medium text-gray-900 dark:text-white"></p>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">From Name</label>
        <input type="text" id="edit-from" name="from" placeholder="Auto Reply" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Subject</label>
        <input type="text" id="edit-subject" name="subject" placeholder="Out of Office" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Body</label>
        <textarea id="edit-body" name="body" rows="3" class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm outline-none focus:border-blue-400 resize-none"></textarea>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Interval (hours)</label>
        <input type="number" id="edit-interval" name="interval" value="1" min="1" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex justify-between pt-1">
        <button type="button" onclick="closeEditModal()" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="button" onclick="submitEdit()" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<script>
function closeAddModal() {
  document.getElementById('modal-add').classList.add('hidden');
  document.getElementById('form-add').reset();
}
function closeEditModal() {
  document.getElementById('modal-edit').classList.add('hidden');
  document.getElementById('form-edit').reset();
}
function openEditModal(email, domain, subject, body, from, interval) {
  document.getElementById('edit-email-hidden').value = email;
  document.getElementById('edit-domain-hidden').value = domain;
  document.getElementById('edit-email-display').textContent = email.includes('@') ? email : email + '@' + domain;
  document.getElementById('edit-subject').value = subject;
  document.getElementById('edit-body').value = body;
  document.getElementById('edit-from').value = from;
  document.getElementById('edit-interval').value = interval;
  document.getElementById('modal-edit').classList.remove('hidden');
}
function submitEdit() {
  const email = document.getElementById('edit-email-hidden').value;
  const domain = document.getElementById('edit-domain-hidden').value;
  const subject = document.getElementById('edit-subject').value;
  const body = document.getElementById('edit-body').value;
  const from = document.getElementById('edit-from').value;
  const interval = document.getElementById('edit-interval').value;
  if (!subject) { alert('Subject is required.'); return; }
  if (!confirm('This will delete the old auto responder and create a new one with your changes. Continue?')) return;
  document.getElementById('edit-delete-action').value = 'delete';
  document.getElementById('form-edit').submit();
}
</script>

<?php include '../includes/layout_end.php'; ?>
