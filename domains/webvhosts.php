<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();

$vhostsResult = $client->call('WebVhosts', 'list_domains');
$vhosts       = $vhostsResult['data'] ?? [];

$sslResult    = $client->call('WebVhosts', 'list_ssl_capable_domains');
$sslCapable   = array_column($sslResult['data'] ?? [], null, 'domain');

$phpResult    = $client->call('LangPHP', 'php_get_vhost_versions');
$phpVersions  = [];
foreach ($phpResult['data'] ?? [] as $entry) {
    $phpVersions[$entry['vhost'] ?? ''] = $entry['version'] ?? '';
}

$search = trim($_GET['q'] ?? '');
$filtered = $search
    ? array_values(array_filter($vhosts, fn($v) => stripos($v['domain'] ?? '', $search) !== false))
    : array_values($vhosts);

$perPage = 15;
$total   = count($filtered);
$pages   = max(1, (int)ceil($total / $perPage));
$page    = max(1, min($pages, (int)($_GET['page'] ?? 1)));
$paged   = array_slice($filtered, ($page - 1) * $perPage, $perPage);

$totalSSL = count(array_filter($vhosts, fn($v) => isset($sslCapable[$v['domain'] ?? ''])));
$noSSL    = count($vhosts) - $totalSSL;

$pageTitle  = 'Virtual Hosts — cPanel Manager';
$activePage = 'domains-webvhosts';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Virtual Hosts</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: WebVhosts/list_domains · WebVhosts/list_ssl_capable_domains</p>
  </div>
  <button onclick="location.reload()" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition">
    <i data-lucide="refresh-cw" class="h-4 w-4"></i> Refresh
  </button>
</div>

<!-- Stats -->
<div class="grid gap-4 sm:grid-cols-3">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20"><i data-lucide="globe-2" class="h-5 w-5 text-blue-600 dark:text-blue-400"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Total vHosts</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= count($vhosts) ?></p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20"><i data-lucide="lock" class="h-5 w-5 text-green-600 dark:text-green-400"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">SSL Capable</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= $totalSSL ?></p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-900/20"><i data-lucide="alert-triangle" class="h-5 w-5 text-amber-600 dark:text-amber-400"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">No SSL</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= $noSSL ?></p></div>
  </div>
</div>

<!-- Search -->
<form method="GET" class="flex gap-3">
  <div class="relative flex-1 max-w-sm">
    <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none"></i>
    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search vhosts…"
      class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 pl-9 pr-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100" />
  </div>
  <button type="submit" class="h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Search</button>
  <?php if ($search): ?>
    <a href="?" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 flex items-center text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50">Clear</a>
  <?php endif; ?>
</form>

<!-- Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Virtual Hosts</h2>
    <span class="text-xs text-gray-400"><?= $total ?> total</span>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Domain</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Document Root</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">SSL Capable</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">PHP</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($paged)): ?>
        <tr><td colspan="6" class="px-5 py-10 text-center text-sm text-gray-400">No virtual hosts found.</td></tr>
        <?php endif; ?>
        <?php foreach ($paged as $v):
          $domain  = $v['domain'] ?? '';
          $type    = $v['type'] ?? 'subdomain';
          $docRoot = $v['documentroot'] ?? $v['homedir'] ?? '—';
          $ssl     = isset($sslCapable[$domain]);
          $php     = $phpVersions[$domain] ?? '';
          $typeBadge = match(strtolower($type)) {
            'main'        => 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
            'addon'       => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400',
            default       => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
          };
          $typeLabel = match(strtolower($type)) {
            'main'  => 'Primary',
            'addon' => 'Addon',
            default => ucfirst($type),
          };
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100">
            <a href="https://<?= htmlspecialchars($domain) ?>" target="_blank" class="hover:text-blue-600 flex items-center gap-1">
              <?= htmlspecialchars($domain) ?> <i data-lucide="external-link" class="h-3 w-3 text-gray-400"></i>
            </a>
          </td>
          <td class="px-5 py-3.5">
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $typeBadge ?>"><?= $typeLabel ?></span>
          </td>
          <td class="px-5 py-3.5 text-sm font-mono text-gray-500 dark:text-gray-400 max-w-xs truncate" title="<?= htmlspecialchars($docRoot) ?>"><?= htmlspecialchars($docRoot) ?></td>
          <td class="px-5 py-3.5">
            <?php if ($ssl): ?>
              <span class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400"><i data-lucide="check-circle" class="h-3.5 w-3.5"></i> Yes</span>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 text-xs text-gray-400"><i data-lucide="x-circle" class="h-3.5 w-3.5"></i> No</span>
            <?php endif; ?>
          </td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $php ? htmlspecialchars($php) : '—' ?></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <a href="ssl.php?domain=<?= urlencode($domain) ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="SSL"><i data-lucide="lock" class="h-4 w-4"></i></a>
              <a href="dns.php?domain=<?= urlencode($domain) ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700" title="DNS"><i data-lucide="network" class="h-4 w-4"></i></a>
            </div>
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

<?php include '../includes/layout_end.php'; ?>
