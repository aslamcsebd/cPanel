<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

$domainsResult = $client->call('DomainInfo', 'list_domains');
$allDomains = array_values(array_filter(array_merge(
    [$domainsResult['data']['main_domain'] ?? ''],
    $domainsResult['data']['addon_domains'] ?? [],
    $domainsResult['data']['sub_domains'] ?? []
)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $r = $client->call('SSL', 'delete_ssl', ['domain' => $_POST['domain'] ?? '']);
        $r['success'] ? $msg = 'Certificate removed.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'install') {
        $r = $client->call('SSL', 'install_ssl', [
            'domain' => $_POST['domain'] ?? '',
            'cert'   => trim($_POST['cert'] ?? ''),
            'key'    => trim($_POST['key'] ?? ''),
            'cabundle' => trim($_POST['cabundle'] ?? ''),
        ]);
        $r['success'] ? $msg = 'Certificate installed.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'autossl') {
        $r = $client->call('SSL', 'start_autossl_check');
        $r['success'] ? $msg = 'AutoSSL check started.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$result = $client->call('SSL', 'list_certs');
$certs  = $result['data'] ?? [];

$now = time();
$valid = $expiring = $expired = 0;
foreach ($certs as $c) {
    $exp  = strtotime($c['not_after'] ?? '');
    $days = ($exp - $now) / 86400;
    if ($days < 0) $expired++;
    elseif ($days < 30) $expiring++;
    else $valid++;
}

// Search & filter
$search    = trim($_GET['q'] ?? '');
$domFilter = $_GET['domain'] ?? '';
$filtered  = array_values(array_filter($certs, function ($c) use ($search, $domFilter) {
    $domain = $c['subject']['commonName'] ?? ($c['domains'][0] ?? '');
    if ($search && stripos($domain, $search) === false) return false;
    if ($domFilter && $domain !== $domFilter) return false;
    return true;
}));

$perPage = 15;
$total   = count($filtered);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = max(1, min($pages, (int)($_GET['page'] ?? 1)));
$paged   = array_slice($filtered, ($page - 1) * $perPage, $perPage);

// Pre-select domain from query string (linked from domains/index)
$preselect = $_GET['domain'] ?? '';

$pageTitle  = 'SSL / TLS — cPanel Manager';
$activePage = 'ssl';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">SSL / TLS</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: SSL/list_certs · SSL/install_ssl · SSL/delete_ssl</p>
  </div>
  <div class="flex gap-2">
    <form method="POST">
      <input type="hidden" name="action" value="autossl" />
      <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">
        <i data-lucide="refresh-cw" class="h-4 w-4"></i> Run AutoSSL
      </button>
    </form>
    <button onclick="document.getElementById('modal-install').classList.remove('hidden')"
      class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
      <i data-lucide="plus" class="h-4 w-4"></i> Install SSL
    </button>
  </div>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Stats -->
<div class="grid gap-4 sm:grid-cols-4">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20"><i data-lucide="shield" class="h-5 w-5 text-blue-600 dark:text-blue-400"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Total Certs</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= count($certs) ?></p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20"><i data-lucide="shield-check" class="h-5 w-5 text-green-600 dark:text-green-400"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Valid</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= $valid ?></p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/20"><i data-lucide="alert-triangle" class="h-5 w-5 text-amber-600 dark:text-amber-400"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Expiring Soon</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= $expiring ?></p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 dark:bg-red-900/20"><i data-lucide="x-circle" class="h-5 w-5 text-red-500 dark:text-red-400"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Expired</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= $expired ?></p></div>
  </div>
</div>

<!-- Search -->
<form method="GET" class="flex flex-wrap gap-3">
  <div class="relative flex-1 min-w-48">
    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none"></i>
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search certificates…"
      class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 pl-9 pr-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100" />
  </div>
  <button type="submit" class="h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Search</button>
  <?php if ($search || $domFilter): ?>
    <a href="?" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 flex items-center text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50">Clear</a>
  <?php endif; ?>
</form>

<!-- Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Certificates</h2>
    <span class="text-xs text-gray-400"><?= $total ?> total</span>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Domain</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Issuer</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Expires</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($paged)): ?>
        <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-400">No certificates found.</td></tr>
        <?php endif; ?>
        <?php foreach ($paged as $c):
          $domain  = $c['subject']['commonName'] ?? ($c['domains'][0] ?? 'N/A');
          $issuer  = $c['issuer']['organizationName'] ?? 'Unknown';
          $expDate = $c['not_after'] ?? '';
          $exp     = strtotime($expDate);
          $days    = (int)(($exp - $now) / 86400);
          if ($days < 0)  { $status = 'Expired';       $badge = 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'; }
          elseif ($days < 30) { $status = 'Expiring Soon'; $badge = 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400'; }
          else            { $status = 'Valid';          $badge = 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'; }
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($domain) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($issuer) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400">
            <?= $expDate ? date('Y-m-d', $exp) . " <span class='text-xs'>({$days}d)</span>" : '—' ?>
          </td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $badge ?>"><?= $status ?></span></td>
          <td class="px-5 py-3.5 text-right">
            <form method="POST" class="inline" onsubmit="return confirm('Remove SSL for <?= htmlspecialchars($domain) ?>?')">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="domain" value="<?= htmlspecialchars($domain) ?>" />
              <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20" title="Remove">
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
    <span>Showing <?= ($page - 1) * $perPage + 1 ?>–<?= min($page * $perPage, $total) ?> of <?= $total ?></span>
    <div class="flex gap-1">
      <?php $qs = fn($p) => '?' . http_build_query(['page' => $p, 'q' => $search]); ?>
      <?php if ($page > 1): ?>
        <a href="<?= $qs($page - 1) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="chevron-left" class="h-4 w-4 text-gray-500"></i></a>
      <?php endif; ?>
      <?php for ($i = max(1, $page - 2); $i <= min($pages, $page + 2); $i++): ?>
        <?php if ($i === $page): ?>
          <span class="h-8 w-8 rounded bg-blue-600 text-white text-sm font-medium flex items-center justify-center"><?= $i ?></span>
        <?php else: ?>
          <a href="<?= $qs($i) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $pages): ?>
        <a href="<?= $qs($page + 1) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="chevron-right" class="h-4 w-4 text-gray-500"></i></a>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Install SSL Modal -->
<div id="modal-install" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Install SSL Certificate</h3>
      <button onclick="document.getElementById('modal-install').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="install" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
        <select name="domain" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
          <?php foreach ($allDomains as $d): ?>
          <option value="<?= htmlspecialchars($d) ?>" <?= $d === $preselect ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Certificate (PEM)</label>
        <textarea name="cert" rows="4" placeholder="-----BEGIN CERTIFICATE-----" required
          class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-xs font-mono outline-none focus:border-blue-400 dark:text-gray-100"></textarea>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Private Key (PEM)</label>
        <textarea name="key" rows="4" placeholder="-----BEGIN PRIVATE KEY-----" required
          class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-xs font-mono outline-none focus:border-blue-400 dark:text-gray-100"></textarea>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">CA Bundle <span class="text-gray-400 font-normal">(optional)</span></label>
        <textarea name="cabundle" rows="3" placeholder="-----BEGIN CERTIFICATE-----"
          class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-xs font-mono outline-none focus:border-blue-400 dark:text-gray-100"></textarea>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal-install').classList.add('hidden')"
          class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Install</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
