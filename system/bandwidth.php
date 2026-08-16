<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();

$result  = $client->call('Bandwidth', 'query');
$bwData  = $result['data'] ?? [];

$totalBytes = $bwData['bytes_used'] ?? 0;
$limitBytes = $bwData['bytes_limit'] ?? 0;
$totalGB    = round($totalBytes / 1024 / 1024 / 1024, 2);
$limitGB    = $limitBytes > 0 ? round($limitBytes / 1024 / 1024 / 1024, 2) : 0;
$pct        = $limitGB > 0 ? min(100, round($totalGB / $limitGB * 100)) : 0;

$domains = $bwData['domains'] ?? [];

$pageTitle  = 'Bandwidth — cPanel Manager';
$activePage = 'bandwidth';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Bandwidth</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Bandwidth/query</p>
  </div>
</div>

<!-- Summary Cards -->
<div class="grid gap-4 sm:grid-cols-3">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Total Used</p>
    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $totalGB ?> GB</p>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Limit</p>
    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $limitGB > 0 ? $limitGB . ' GB' : 'Unlimited' ?></p>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Usage</p>
    <p class="text-2xl font-semibold text-gray-900 dark:text-white"><?= $pct > 0 ? $pct . '%' : 'N/A' ?></p>
    <?php if ($pct > 0): ?>
    <div class="mt-2 h-2 rounded-full bg-gray-100 dark:bg-gray-700">
      <div class="h-2 rounded-full <?= $pct > 80 ? 'bg-red-500' : 'bg-blue-500' ?>" style="width:<?= $pct ?>%"></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Per-domain breakdown -->
<?php if (!empty($domains)): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="px-5 py-4 border-b border-gray-100 dark:border-gray-700">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Bandwidth by Domain</h2>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Domain</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Used</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Usage</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php foreach ($domains as $domain => $bytes):
          $gb = round($bytes / 1024 / 1024 / 1024, 3);
          $domainPct = $totalBytes > 0 ? round($bytes / $totalBytes * 100) : 0;
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($domain) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300"><?= $gb ?> GB</td>
          <td class="px-5 py-3.5 w-48">
            <div class="flex items-center gap-2">
              <div class="flex-1 h-2 rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-2 rounded-full bg-blue-500" style="width:<?= max(1, $domainPct) ?>%"></div>
              </div>
              <span class="text-xs text-gray-500 dark:text-gray-400 w-8"><?= $domainPct ?>%</span>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php else: ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-10 text-center shadow-sm">
  <i data-lucide="bar-chart-2" class="h-10 w-10 text-gray-300 mx-auto mb-3"></i>
  <p class="text-sm text-gray-500 dark:text-gray-400">No per-domain bandwidth data available.</p>
</div>
<?php endif; ?>

<?php include '../includes/layout_end.php'; ?>
