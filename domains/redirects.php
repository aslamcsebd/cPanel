<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

$domainsResult = $client->call('DomainInfo', 'list_domains');
$domains = array_values(array_filter(array_merge(
    [$domainsResult['data']['main_domain'] ?? ''],
    $domainsResult['data']['addon_domains'] ?? [],
    $domainsResult['data']['sub_domains'] ?? []
)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $params = [
            'domain'       => $_POST['domain'] ?? '',
            'redirect'     => trim($_POST['redirect'] ?? ''),
            'redirect_url' => trim($_POST['redirect_url'] ?? ''),
            'type'         => $_POST['type'] ?? 'permanent',
            'redirect_wildcard' => isset($_POST['wildcard']) ? 1 : 0,
            'redirect_www'      => isset($_POST['www']) ? 1 : 0,
        ];
        $r = $client->call('Mime', 'add_redirect', $params);
        $r['success'] ? $msg = 'Redirect added.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete') {
        $r = $client->call('Mime', 'delete_redirect', ['domain' => $_POST['domain'] ?? '', 'redirect' => $_POST['redirect'] ?? '']);
        $r['success'] ? $msg = 'Redirect deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$result    = $client->call('Mime', 'list_redirects');
$redirects = $result['data'] ?? [];

$perPage = 15;
$total   = count($redirects);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = max(1, min($pages, (int)($_GET['page'] ?? 1)));
$paged   = array_slice($redirects, ($page - 1) * $perPage, $perPage);

$pageTitle  = 'Redirects — cPanel Manager';
$activePage = 'redirects';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Redirects</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Mime/list_redirects · Mime/add_redirect · Mime/delete_redirect</p>
  </div>
  <button onclick="document.getElementById('modal-add').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition">
    <i data-lucide="plus" class="h-4 w-4"></i> Add Redirect
  </button>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Stats bar -->
<div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
  <?php
  $permanent = count(array_filter($redirects, fn($r) => ($r['type'] ?? '') === 'permanent'));
  $temp      = $total - $permanent;
  ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 shadow-sm">
    <p class="text-xs text-gray-500 dark:text-gray-400">Total</p>
    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $total ?></p>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 shadow-sm">
    <p class="text-xs text-gray-500 dark:text-gray-400">Permanent (301)</p>
    <p class="text-2xl font-semibold text-blue-600 dark:text-blue-400"><?= $permanent ?></p>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 shadow-sm">
    <p class="text-xs text-gray-500 dark:text-gray-400">Temporary (302)</p>
    <p class="text-2xl font-semibold text-amber-600 dark:text-amber-400"><?= $temp ?></p>
  </div>
</div>

<!-- Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">All Redirects</h2>
    <span class="text-xs text-gray-400"><?= $total ?> total</span>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Source</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Destination</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Wildcard</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($paged)): ?>
        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">No redirects configured.</td></tr>
        <?php endif; ?>
        <?php foreach ($paged as $rd):
          $src     = ($rd['domain'] ?? '') . ($rd['redirect'] ?? '');
          $dest    = $rd['redirect_url'] ?? '';
          $type    = $rd['type'] ?? 'permanent';
          $wild    = !empty($rd['redirect_wildcard']);
          $typeBadge = $type === 'permanent'
            ? 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400'
            : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400';
          $typeLabel = $type === 'permanent' ? '301 Permanent' : '302 Temporary';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-mono text-gray-900 dark:text-gray-100 max-w-xs truncate"><?= htmlspecialchars($src) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300 max-w-xs truncate">
            <a href="<?= htmlspecialchars($dest) ?>" target="_blank" class="hover:text-blue-600 flex items-center gap-1">
              <?= htmlspecialchars($dest) ?> <i data-lucide="external-link" class="h-3 w-3 flex-shrink-0"></i>
            </a>
          </td>
          <td class="px-5 py-3.5">
            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold <?= $typeBadge ?>"><?= $typeLabel ?></span>
          </td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">
            <?= $wild ? '<span class="text-green-600 dark:text-green-400">Yes</span>' : '<span class="text-gray-400">No</span>' ?>
          </td>
          <td class="px-5 py-3.5 text-right">
            <form method="POST" class="inline" onsubmit="return confirm('Delete this redirect?')">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="domain" value="<?= htmlspecialchars($rd['domain'] ?? '') ?>" />
              <input type="hidden" name="redirect" value="<?= htmlspecialchars($rd['redirect'] ?? '') ?>" />
              <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                <i data-lucide="trash-2" class="h-4 w-4"></i>
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pages > 1): ?>
  <div class="px-5 py-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between text-sm text-gray-500">
    <span>Page <?= $page ?> of <?= $pages ?></span>
    <div class="flex gap-1">
      <?php for ($i = 1; $i <= $pages; $i++): ?>
      <a href="?page=<?= $i ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-lg <?= $i === $page ? 'bg-blue-600 text-white' : 'hover:bg-gray-100 dark:hover:bg-gray-700 text-gray-600 dark:text-gray-400' ?>"><?= $i ?></a>
      <?php endfor; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Add Modal -->
<div id="modal-add" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Redirect</h3>
      <button onclick="document.getElementById('modal-add').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="add" />
      <div class="grid gap-4 sm:grid-cols-2">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
          <select name="domain" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
            <option value="">— All Public Domains —</option>
            <?php foreach ($domains as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
          <select name="type" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
            <option value="permanent">301 Permanent</option>
            <option value="temp">302 Temporary</option>
          </select>
        </div>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Source Path <span class="text-gray-400 font-normal">(e.g. /old-page)</span></label>
        <input type="text" name="redirect" placeholder="/old-page" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Redirect To (URL)</label>
        <input type="url" name="redirect_url" placeholder="https://example.com/new-page" required class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex items-center gap-6">
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
          <input type="checkbox" name="wildcard" class="rounded border-gray-300 text-blue-600" /> Wildcard redirect
        </label>
        <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
          <input type="checkbox" name="www" class="rounded border-gray-300 text-blue-600" /> Redirect www
        </label>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal-add').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add Redirect</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
