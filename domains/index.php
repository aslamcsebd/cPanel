<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $r = $client->call('AddonDomain', 'addaddondomain', [
            'newdomain' => trim($_POST['domain'] ?? ''),
            'subdomain' => trim($_POST['subdomain'] ?? ''),
            'dir'       => trim($_POST['dir'] ?? ''),
        ]);
        $r['success'] ? $msg = 'Domain added.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete') {
        $r = $client->call('AddonDomain', 'deladdondomain', [
            'domain'    => $_POST['domain'] ?? '',
            'subdomain' => $_POST['subdomain'] ?? '',
        ]);
        $r['success'] ? $msg = 'Domain removed.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$result  = $client->call('DomainInfo', 'list_domains');
$data    = $result['data'] ?? [];
$primary = $data['main_domain'] ?? '';
$addons  = $data['addon_domains'] ?? [];
$subs    = $data['sub_domains'] ?? [];

$sslResult   = $client->call('SSL', 'list_certs');
$certDomains = [];
foreach ($sslResult['data'] ?? [] as $cert) {
    foreach ((array)($cert['domains'] ?? []) as $d) $certDomains[] = $d;
}

// Build unified list
$allDomains = [];
if ($primary) $allDomains[] = ['domain' => $primary, 'type' => 'Primary'];
foreach ($addons as $d) $allDomains[] = ['domain' => $d, 'type' => 'Addon'];
foreach ($subs   as $d) $allDomains[] = ['domain' => $d, 'type' => 'Subdomain'];

// Search
$search = trim($_GET['q'] ?? '');
$typeFilter = $_GET['type'] ?? '';
$filtered = array_filter($allDomains, function ($row) use ($search, $typeFilter) {
    if ($search && stripos($row['domain'], $search) === false) return false;
    if ($typeFilter && $row['type'] !== $typeFilter) return false;
    return true;
});
$filtered = array_values($filtered);

// Pagination
$perPage    = 12;
$total      = count($filtered);
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset     = ($page - 1) * $perPage;
$paged      = array_slice($filtered, $offset, $perPage);

$sslCount = count(array_filter($allDomains, fn($r) => in_array($r['domain'], $certDomains)));

$pageTitle  = 'Domains — cPanel Manager';
$activePage = 'domains';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Domains</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage your primary, addon, and subdomains</p>
  </div>
  <button onclick="document.getElementById('modal-add').classList.remove('hidden')"
    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Add Domain
  </button>
</div>

<?php if ($msg): ?>
<div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2">
  <i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>
<?php if ($err): ?>
<div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2">
  <i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<!-- Stat Cards -->
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
  <?php
  $stats = [
    ['Total Domains', count($allDomains), 'globe-2',    'blue'],
    ['Primary',       $primary ? 1 : 0,  'home',       'indigo'],
    ['Addon',         count($addons),     'plus-circle','violet'],
    ['SSL Secured',   $sslCount,          'shield-check','green'],
  ];
  $palette = [
    'blue'   => ['bg-blue-50 dark:bg-blue-900/20',   'text-blue-600 dark:text-blue-400'],
    'indigo' => ['bg-indigo-50 dark:bg-indigo-900/20','text-indigo-600 dark:text-indigo-400'],
    'violet' => ['bg-violet-50 dark:bg-violet-900/20','text-violet-600 dark:text-violet-400'],
    'green'  => ['bg-green-50 dark:bg-green-900/20',  'text-green-600 dark:text-green-400'],
  ];
  foreach ($stats as [$label, $value, $icon, $color]):
    [$bg, $tc] = $palette[$color];
  ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= $label ?></p>
        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= $value ?></p>
      </div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg <?= $bg ?>">
        <i data-lucide="<?= $icon ?>" class="h-6 w-6 <?= $tc ?>"></i>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Search & Filter -->
<div class="flex flex-col sm:flex-row gap-3">
  <form method="GET" class="flex flex-1 gap-3">
    <div class="relative flex-1">
      <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none"></i>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search domains…"
        class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 pl-9 pr-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100" />
    </div>
    <select name="type" onchange="this.form.submit()"
      class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100">
      <option value="">All Types</option>
      <?php foreach (['Primary','Addon','Subdomain'] as $t): ?>
        <option value="<?= $t ?>" <?= $typeFilter === $t ? 'selected' : '' ?>><?= $t ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Search</button>
    <?php if ($search || $typeFilter): ?>
      <a href="?" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 flex items-center text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50">Clear</a>
    <?php endif; ?>
  </form>
</div>

<!-- Domain Cards -->
<?php if (empty($paged)): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-12 text-center text-sm text-gray-400">
  No domains found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.
</div>
<?php else: ?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  <?php foreach ($paged as $row):
    $domain = $row['domain'];
    $type   = $row['type'];
    $hasSSL = in_array($domain, $certDomains);
    [$iconBg, $iconTc] = match($type) {
      'Primary'   => ['bg-blue-50',   'text-blue-600'],
      'Addon'     => ['bg-indigo-50', 'text-indigo-600'],
      default     => ['bg-violet-50', 'text-violet-600'],
    };
  ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-blue-400 dark:hover:border-blue-500 transition flex flex-col gap-4">
    <!-- Dashboard-style top: label top-left, value below, icon top-right -->
    <div class="flex items-center justify-between">
      <div class="min-w-0 pr-3">
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= $type ?></p>
        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white truncate" title="<?= htmlspecialchars($domain) ?>"><?= htmlspecialchars($domain) ?></p>
      </div>
      <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg <?= $iconBg ?>">
        <i data-lucide="globe-2" class="h-6 w-6 <?= $iconTc ?>"></i>
      </div>
    </div>

    <!-- Meta -->
    <div class="flex items-center gap-3">
      <span class="inline-flex items-center gap-1 text-xs font-medium <?= $hasSSL ? 'text-green-600 dark:text-green-400' : 'text-gray-400 dark:text-gray-500' ?>">
        <i data-lucide="<?= $hasSSL ? 'lock' : 'lock-open' ?>" class="h-3 w-3"></i>
        <?= $hasSSL ? 'SSL Active' : 'No SSL' ?>
      </span>
      <a href="https://<?= htmlspecialchars($domain) ?>" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs text-blue-500 hover:underline">
        Visit <i data-lucide="external-link" class="h-3 w-3"></i>
      </a>
    </div>

    <!-- Actions -->
    <div class="flex flex-wrap gap-2 border-t border-gray-100 dark:border-gray-700 pt-3">
      <a href="dns.php?domain=<?= urlencode($domain) ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="network" class="h-3.5 w-3.5"></i> DNS</a>
      <a href="ssl.php?domain=<?= urlencode($domain) ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="shield" class="h-3.5 w-3.5"></i> SSL</a>
      <a href="subdomains.php?domain=<?= urlencode($domain) ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="layers" class="h-3.5 w-3.5"></i> Subs</a>
      <a href="redirects.php?domain=<?= urlencode($domain) ?>" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="corner-up-right" class="h-3.5 w-3.5"></i> Redirects</a>
      <?php if ($type !== 'Primary'): ?>
      <form method="POST" class="ml-auto" onsubmit="return confirm('Delete <?= htmlspecialchars($domain) ?>?')">
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" name="domain" value="<?= htmlspecialchars($domain) ?>" />
        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 dark:border-red-800 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
  <span class="text-sm text-gray-500 dark:text-gray-400">
    Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> domains
  </span>
  <div class="flex items-center gap-1">
    <?php
    $qs = fn($p) => '?' . http_build_query(['page' => $p, 'q' => $search, 'type' => $typeFilter]);
    ?>
    <?php if ($page > 1): ?>
      <a href="<?= $qs(1) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="chevrons-left" class="h-4 w-4 text-gray-500"></i></a>
      <a href="<?= $qs($page - 1) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="chevron-left" class="h-4 w-4 text-gray-500"></i></a>
    <?php endif; ?>
    <?php
    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);
    if ($start > 1) echo '<span class="h-8 px-1 flex items-center text-gray-400 text-sm">…</span>';
    for ($i = $start; $i <= $end; $i++):
    ?>
      <?php if ($i === $page): ?>
        <span class="h-8 w-8 rounded bg-blue-600 text-white text-sm font-medium flex items-center justify-center"><?= $i ?></span>
      <?php else: ?>
        <a href="<?= $qs($i) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($end < $totalPages) echo '<span class="h-8 px-1 flex items-center text-gray-400 text-sm">…</span>'; ?>
    <?php if ($page < $totalPages): ?>
      <a href="<?= $qs($page + 1) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="chevron-right" class="h-4 w-4 text-gray-500"></i></a>
      <a href="<?= $qs($totalPages) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="chevrons-right" class="h-4 w-4 text-gray-500"></i></a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Add Domain Modal -->
<div id="modal-add" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Addon Domain</h3>
      <button onclick="document.getElementById('modal-add').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="add" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Domain Name</label>
        <input type="text" name="domain" placeholder="newdomain.com"
          class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Subdomain (auto-created)</label>
        <input type="text" name="subdomain" placeholder="newdomain"
          class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Document Root</label>
        <input type="text" name="dir" placeholder="/public_html/newdomain"
          class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal-add').classList.add('hidden')"
          class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add Domain</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
