<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

// Root domains for add modal
$domainsResult  = $client->call('DomainInfo', 'list_domains');
$domainData     = $domainsResult['data'] ?? [];
$allRootDomains = array_values(array_filter(array_merge(
    [$domainData['main_domain'] ?? ''],
    $domainData['addon_domains'] ?? []
)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $r = $client->call('SubDomain', 'addsubdomain', [
            'domain'     => trim($_POST['subdomain'] ?? ''),
            'rootdomain' => trim($_POST['rootdomain'] ?? ''),
            'dir'        => trim($_POST['dir'] ?? ''),
        ]);
        $r['success'] ? $msg = 'Subdomain created.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete') {
        // SubDomain/delsubdomain only exists in API2
        $r = $client->callApi2('SubDomain', 'delsubdomain', ['domain' => $_POST['domain'] ?? '']);
        $r['success'] ? $msg = 'Subdomain deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

// DomainInfo/domains_data returns full subdomain details with documentroot, phpversion, status
$result     = $client->call('DomainInfo', 'domains_data');
$subdomains = $result['data']['sub_domains'] ?? [];

// Search & filter
$search     = trim($_GET['q'] ?? '');
$rootFilter = $_GET['root'] ?? '';
$filtered   = array_values(array_filter($subdomains, function ($s) use ($search, $rootFilter) {
    $domain = $s['domain'] ?? '';
    if ($search && stripos($domain, $search) === false) return false;
    if ($rootFilter) {
        // rootdomain = everything after first dot
        $dot  = strpos($domain, '.');
        $root = $dot !== false ? substr($domain, $dot + 1) : '';
        if ($root !== $rootFilter) return false;
    }
    return true;
}));

// Build root domain list for filter dropdown
$rootDomains = array_values(array_unique(array_map(function ($s) {
    $domain = $s['domain'] ?? '';
    $dot    = strpos($domain, '.');
    return $dot !== false ? substr($domain, $dot + 1) : '';
}, $subdomains)));
sort($rootDomains);

// Pagination
$perPage    = 12;
$total      = count($filtered);
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset     = ($page - 1) * $perPage;
$paged      = array_slice($filtered, $offset, $perPage);

$pageTitle  = 'Subdomains — cPanel Manager';
$activePage = 'subdomains';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Subdomains</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: DomainInfo/domains_data · SubDomain/addsubdomain</p>
  </div>
  <button onclick="document.getElementById('modal-add').classList.remove('hidden')"
    class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Add Subdomain
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
<div class="grid gap-4 sm:grid-cols-3">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <div><p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Subdomains</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= count($subdomains) ?></p></div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20"><i data-lucide="layers" class="h-6 w-6 text-blue-600 dark:text-blue-400"></i></div>
    </div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <div><p class="text-sm font-medium text-gray-500 dark:text-gray-400">Root Domains</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= count($rootDomains) ?></p></div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/20"><i data-lucide="globe-2" class="h-6 w-6 text-indigo-600 dark:text-indigo-400"></i></div>
    </div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <div><p class="text-sm font-medium text-gray-500 dark:text-gray-400">Showing</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= $total ?></p></div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/20"><i data-lucide="filter" class="h-6 w-6 text-violet-600 dark:text-violet-400"></i></div>
    </div>
  </div>
</div>

<!-- Search & Filter -->
<form method="GET" class="flex flex-wrap gap-3">
  <div class="relative flex-1 min-w-48">
    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none"></i>
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search subdomains…"
      class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 pl-9 pr-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100" />
  </div>
  <select name="root" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100">
    <option value="">All Root Domains</option>
    <?php foreach ($rootDomains as $rd): ?>
      <option value="<?= htmlspecialchars($rd) ?>" <?= $rootFilter === $rd ? 'selected' : '' ?>><?= htmlspecialchars($rd) ?></option>
    <?php endforeach; ?>
  </select>
  <button type="submit" class="h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Search</button>
  <?php if ($search || $rootFilter): ?>
    <a href="?" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 flex items-center text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50">Clear</a>
  <?php endif; ?>
</form>

<!-- Subdomain Cards -->
<?php if (empty($paged)): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-5 py-12 text-center text-sm text-gray-400">
  No subdomains found<?= $search ? ' matching "' . htmlspecialchars($search) . '"' : '' ?>.
</div>
<?php else: ?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  <?php foreach ($paged as $sub):
    $fullDomain = $sub['domain'] ?? '';
    $dot        = strpos($fullDomain, '.');
    $subdomain  = $dot !== false ? substr($fullDomain, 0, $dot) : $fullDomain;
    $rootdomain = $dot !== false ? substr($fullDomain, $dot + 1) : '';
    $docRoot    = $sub['documentroot'] ?? '—';
    $phpVer     = $sub['phpversion'] ?? '';
    $sslRedirect = !empty($sub['ssl_redirect']) && $sub['ssl_redirect'] !== '0';
    $status     = $sub['status'] ?? '';
  ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-blue-400 dark:hover:border-blue-500 transition flex flex-col gap-3">
    <div class="flex items-center justify-between">
      <div class="min-w-0 pr-3">
        <p class="text-xs font-medium text-gray-400 dark:text-gray-500"><?= htmlspecialchars($rootdomain) ?></p>
        <p class="mt-0.5 text-lg font-semibold text-gray-900 dark:text-white truncate" title="<?= htmlspecialchars($fullDomain) ?>"><?= htmlspecialchars($subdomain) ?></p>
        <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($fullDomain) ?></p>
      </div>
      <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20">
        <i data-lucide="layers" class="h-5 w-5 text-blue-600 dark:text-blue-400"></i>
      </div>
    </div>

    <!-- Meta -->
    <div class="flex flex-wrap gap-2 text-xs">
      <span class="inline-flex items-center gap-1 text-gray-500 dark:text-gray-400">
        <i data-lucide="folder" class="h-3 w-3"></i>
        <span class="truncate max-w-[140px] font-mono" title="<?= htmlspecialchars($docRoot) ?>"><?= htmlspecialchars($docRoot) ?></span>
      </span>
      <?php if ($phpVer): ?>
      <span class="inline-flex items-center gap-1 rounded bg-violet-50 dark:bg-violet-900/20 px-1.5 py-0.5 text-violet-700 dark:text-violet-400 font-medium">
        <?= htmlspecialchars($phpVer) ?>
      </span>
      <?php endif; ?>
      <?php if ($sslRedirect): ?>
      <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400">
        <i data-lucide="lock" class="h-3 w-3"></i> SSL
      </span>
      <?php endif; ?>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-2 border-t border-gray-100 dark:border-gray-700 pt-3">
      <a href="https://<?= htmlspecialchars($fullDomain) ?>" target="_blank" rel="noopener"
        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
        <i data-lucide="external-link" class="h-3.5 w-3.5"></i> Visit
      </a>
      <a href="dns.php?domain=<?= urlencode($rootdomain) ?>"
        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
        <i data-lucide="network" class="h-3.5 w-3.5"></i> DNS
      </a>
      <form method="POST" class="ml-auto" onsubmit="return confirm('Delete <?= htmlspecialchars($fullDomain) ?>?')">
        <input type="hidden" name="action" value="delete" />
        <input type="hidden" name="domain" value="<?= htmlspecialchars($fullDomain) ?>" />
        <button type="submit"
          class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 dark:border-red-800 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">
          <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete
        </button>
      </form>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-2">
  <span class="text-sm text-gray-500 dark:text-gray-400">
    Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $total) ?> of <?= $total ?> subdomains
  </span>
  <div class="flex items-center gap-1">
    <?php $qs = fn($p) => '?' . http_build_query(['page' => $p, 'q' => $search, 'root' => $rootFilter]); ?>
    <?php if ($page > 1): ?>
      <a href="<?= $qs(1) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="chevrons-left" class="h-4 w-4 text-gray-500"></i></a>
      <a href="<?= $qs($page - 1) ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600"><i data-lucide="chevron-left" class="h-4 w-4 text-gray-500"></i></a>
    <?php endif; ?>
    <?php
    $start = max(1, $page - 2); $end = min($totalPages, $page + 2);
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

<!-- Add Subdomain Modal -->
<div id="modal-add" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Subdomain</h3>
      <button onclick="document.getElementById('modal-add').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="add" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Subdomain</label>
        <input type="text" name="subdomain" placeholder="blog" required
          class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Root Domain</label>
        <select name="rootdomain" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
          <?php foreach ($allRootDomains as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Document Root</label>
        <input type="text" name="dir" placeholder="/public_html/blog"
          class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal-add').classList.add('hidden')"
          class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
