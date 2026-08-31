<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

$popsResult  = $client->call('Email', 'list_pops');
$allAccounts = array_map(fn($a) => $a['email'] ?? ($a['login'] . '@' . $a['domain']), $popsResult['data'] ?? []);

$currentEmail = $_GET['account'] ?? ($_SESSION['filter_current'] ?? '');
if (!$currentEmail && !empty($allAccounts)) $currentEmail = $allAccounts[0];
if ($currentEmail) $_SESSION['filter_current'] = $currentEmail;

// Group by base username (same as mailbox.php)
$_baseCounts = [];
foreach ($allAccounts as $acc) {
    $user = strstr($acc, '@', true);
    $base = strtolower(preg_replace('/[._\-].*$|\d+.*$/', '', $user) ?: $user);
    $_baseCounts[$base] = ($_baseCounts[$base] ?? 0) + 1;
}
$groupedAccounts = ['Others' => []];
foreach ($allAccounts as $acc) {
    $user = strstr($acc, '@', true);
    $base = strtolower(preg_replace('/[._\-].*$|\d+.*$/', '', $user) ?: $user);
    if ($_baseCounts[$base] >= 3) {
        $groupedAccounts[ucfirst($base)][] = $acc;
    } else {
        $groupedAccounts['Others'][] = $acc;
    }
}
if (empty($groupedAccounts['Others'])) unset($groupedAccounts['Others']);
else { $tmp = $groupedAccounts['Others']; unset($groupedAccounts['Others']); ksort($groupedAccounts); $groupedAccounts['Others'] = $tmp; }

$currentGroup = 'Others';
foreach ($groupedAccounts as $grp => $emails) {
    if (in_array($currentEmail, $emails)) { $currentGroup = $grp; break; }
}

$domainsResult = $client->call('DomainInfo', 'list_domains');
$domains = array_filter(array_merge(
    [$domainsResult['data']['main_domain'] ?? ''],
    $domainsResult['data']['addon_domains'] ?? []
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $r = $client->call('Email', 'store_filter', [
            'account' => $currentEmail,
            'filtername' => trim($_POST['filtername'] ?? ''),
            'rule1' => trim($_POST['part1'] ?? ''),
            'match1' => trim($_POST['match1'] ?? ''),
            'value1' => trim($_POST['val1'] ?? ''),
            'action1' => trim($_POST['action1'] ?? ''),
            'destination1' => trim($_POST['dest1'] ?? ''),
        ]);
        $r['success'] ? $msg = 'Filter created.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete') {
        $r = $client->call('Email', 'delete_filter', [
            'account' => $currentEmail,
            'filtername' => $_POST['filtername'] ?? '',
        ]);
        $r['success'] ? $msg = 'Filter deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$filters = [];
if ($currentEmail) {
    $result = $client->call('Email', 'list_filters', ['account' => $currentEmail]);
    $filters = $result['data'] ?? [];
}

$perPage = 10;
$totalFilters = count($filters);
$totalPages = max(1, (int)ceil($totalFilters / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$pagedFilters = array_slice($filters, $offset, $perPage);

$pageTitle  = 'Email Filters — cPanel Manager';
$activePage = 'email-filters';
include '../includes/layout.php';
?>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="grid gap-4 lg:grid-cols-5">

  <!-- Sidebar -->
  <div class="lg:col-span-1 space-y-3">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-3">
      <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Account</p>
      <div class="space-y-2">
        <select id="sb-group" onchange="filterEmails('sb-email',this.value)" class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <?php foreach (array_keys($groupedAccounts) as $grp): ?>
          <option value="<?= htmlspecialchars($grp) ?>" <?= $grp === $currentGroup ? 'selected' : '' ?>><?= htmlspecialchars($grp) ?> (<?= count($groupedAccounts[$grp]) ?>)</option>
          <?php endforeach; ?>
        </select>
        <select id="sb-email" name="account" class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <?php foreach ($groupedAccounts[$currentGroup] as $acc): ?>
          <option value="<?= htmlspecialchars($acc) ?>" <?= $acc === $currentEmail ? 'selected' : '' ?>><?= htmlspecialchars($acc) ?></option>
          <?php endforeach; ?>
        </select>
        <button onclick="window.location='?account='+encodeURIComponent(document.getElementById('sb-email').value)" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-2 text-sm font-medium text-white">
          <i data-lucide="filter" class="h-4 w-4"></i> Show Filters
        </button>
      </div>
    </div>

    <!-- Stats -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-3">
      <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Stats</p>
      <div class="space-y-2">
        <div class="flex items-center justify-between text-sm">
          <span class="text-gray-500">Filters</span>
          <span class="font-medium text-gray-900 dark:text-white"><?= $totalFilters ?></span>
        </div>
        <div class="flex items-center justify-between text-sm">
          <span class="text-gray-500">Domains</span>
          <span class="font-medium text-gray-900 dark:text-white"><?= count($domains) ?></span>
        </div>
        <div class="flex items-center justify-between text-sm">
          <span class="text-gray-500">Accounts</span>
          <span class="font-medium text-gray-900 dark:text-white"><?= count($allAccounts) ?></span>
        </div>
      </div>
    </div>

    <button onclick="openAddModal()" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
      <i data-lucide="plus" class="h-4 w-4"></i> Add Filter
    </button>
  </div>

  <!-- Main Content -->
  <div class="lg:col-span-4">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
      <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Filters for <?= htmlspecialchars($currentEmail) ?></h2>
        <span class="text-xs text-gray-400"><?= $totalFilters ?> total</span>
      </div>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
          <thead class="bg-gray-50 dark:bg-gray-700/50">
            <tr>
              <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Filter Name</th>
              <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Condition</th>
              <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Action</th>
              <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php if (empty($pagedFilters)): ?>
            <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">No filters found for this account.</td></tr>
            <?php endif; ?>
            <?php foreach ($pagedFilters as $f):
              $filtername = $f['filtername'] ?? '';
              $condition  = ($f['part1'] ?? '') . ' ' . ($f['match1'] ?? '') . ' ' . ($f['val1'] ?? '');
              $action     = $f['action1'] ?? '';
              $dest       = $f['dest1'] ?? '';
            ?>
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($filtername) ?></td>
              <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300 font-mono text-xs"><?= htmlspecialchars(trim($condition)) ?></td>
              <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">
                <?= htmlspecialchars($action) ?><?= $dest ? ' → ' . htmlspecialchars($dest) : '' ?>
              </td>
              <td class="px-5 py-3.5 text-right">
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="delete" />
                  <input type="hidden" name="filtername" value="<?= htmlspecialchars($filtername) ?>" />
                  <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
                </form>
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
      <span class="text-sm text-gray-500 dark:text-gray-400">Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalFilters) ?> of <?= $totalFilters ?> filters</span>
      <div class="flex items-center gap-1">
        <?php if ($page > 1): ?>
          <a href="?page=1&account=<?= urlencode($currentEmail) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="First"><i data-lucide="chevrons-left" class="h-4 w-4 text-gray-500"></i></a>
          <a href="?page=<?= $page - 1 ?>&account=<?= urlencode($currentEmail) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="Previous"><i data-lucide="chevron-left" class="h-4 w-4 text-gray-500"></i></a>
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
            <a href="?page=<?= $i ?>&account=<?= urlencode($currentEmail) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($end < $totalPages) echo '<span class="h-8 w-8 flex items-center justify-center text-gray-400">…</span>'; ?>
        <?php if ($page < $totalPages): ?>
          <a href="?page=<?= $page + 1 ?>&account=<?= urlencode($currentEmail) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="Next"><i data-lucide="chevron-right" class="h-4 w-4 text-gray-500"></i></a>
          <a href="?page=<?= $totalPages ?>&account=<?= urlencode($currentEmail) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="Last"><i data-lucide="chevrons-right" class="h-4 w-4 text-gray-500"></i></a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Add Filter Modal -->
<div id="modal-add" class="hidden fixed inset-0 z-50 flex items-center justify-center">
  <div class="fixed inset-0 bg-black/70" onclick="closeAddModal()"></div>
  <div class="relative z-50 w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl mx-4">
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-t-xl">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Email Filter</h3>
      <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" id="form-add" class="p-5 space-y-3" autocomplete="off">
      <input type="hidden" name="action" value="create" />
      <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-3 py-2">
        <p class="text-xs text-gray-500">Account</p>
        <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($currentEmail) ?></p>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Filter Name</label>
        <input type="text" name="filtername" placeholder="e.g. Block Spam" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Condition</label>
        <div class="grid grid-cols-3 gap-2">
          <select name="part1" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
            <option value="Subject">Subject</option>
            <option value="From">From</option>
            <option value="To">To</option>
            <option value="Body">Body</option>
            <option value="Any Header">Any Header</option>
          </select>
          <select name="match1" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
            <option value="contains">contains</option>
            <option value="does not contain">does not contain</option>
            <option value="begins with">begins with</option>
            <option value="ends with">ends with</option>
            <option value="matches regex">matches regex</option>
          </select>
          <input type="text" name="val1" placeholder="value" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
        </div>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Action</label>
        <select name="action1" id="filter-action" onchange="toggleActionTarget()" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
          <option value="deliver">Deliver to</option>
          <option value="fail">Fail with message</option>
          <option value="pipe">Pipe to program</option>
        </select>
      </div>
      <div id="action-target">
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Destination</label>
        <input type="text" name="dest1" placeholder="Folder name, email address, or message" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex justify-between pt-1">
        <button type="button" onclick="closeAddModal()" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create Filter</button>
      </div>
    </form>
  </div>
</div>

<script>
function openAddModal() {
  document.getElementById('modal-add').classList.remove('hidden');
}
function closeAddModal() {
  document.getElementById('modal-add').classList.add('hidden');
  document.getElementById('form-add').reset();
  document.getElementById('action-target').classList.remove('hidden');
}
function toggleActionTarget() {
  var action = document.getElementById('filter-action').value;
  document.getElementById('action-target').classList.toggle('hidden', action === 'fail');
}
function filterEmails(emailId, group) {
  var emails = <?= json_encode($groupedAccounts, JSON_HEX_TAG) ?>;
  var select = document.getElementById(emailId);
  select.innerHTML = '';
  if (emails[group]) {
    emails[group].forEach(function(acc) {
      var opt = document.createElement('option');
      opt.value = acc;
      opt.textContent = acc;
      select.appendChild(opt);
    });
  }
}
</script>

<?php include '../includes/layout_end.php'; ?>
