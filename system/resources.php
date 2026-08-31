<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();

$quotaResult = $client->call('Quota', 'get_quota_info');
$quota       = $quotaResult['data'] ?? [];

$usageResult = $client->call('ResourceUsage', 'get_usages');
$usages      = $usageResult['data'] ?? [];

$emailResult = $client->call('Email', 'count_pops');
$emailCount  = $emailResult['data']['count'] ?? 0;

$dbResult  = $client->call('Mysql', 'list_databases');
$dbCount   = count($dbResult['data'] ?? []);

$ftpResult = $client->call('Ftp', 'list_ftp');
$ftpCount  = count($ftpResult['data'] ?? []);

$subResult = $client->call('DomainInfo', 'list_domains');
$subCount  = count($subResult['data']['sub_domains'] ?? []);

$diskUsed  = round(($quota['megabytes_used'] ?? 0), 1);
$diskLimit = ($quota['megabytes_limit'] ?? 0) == 0 ? 0 : round($quota['megabytes_limit'], 1);
$diskPct   = $diskLimit > 0 ? min(100, round($diskUsed / $diskLimit * 100)) : 0;

$bwResult  = $client->call('Bandwidth', 'query');
$bwData    = $bwResult['data'] ?? [];
$bwUsed    = round(($bwData['bytes_used'] ?? 0) / 1024 / 1024 / 1024, 2);
$bwLimit   = ($bwData['bytes_limit'] ?? 0) == 0 ? 0 : round($bwData['bytes_limit'] / 1024 / 1024 / 1024, 2);
$bwPct     = $bwLimit > 0 ? min(100, round($bwUsed / $bwLimit * 100)) : 0;

$pageTitle  = 'Resource Usage — cPanel Manager';
$activePage = 'resource-usage';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Resource Usage</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Quota/get_quota_info, ResourceUsage/get_usages</p>
  </div>
  <a href="javascript:void(0)" onclick="location.reload()" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">
    <i data-lucide="refresh-cw" class="h-4 w-4"></i> Refresh
  </a>
</div>

<!-- Usage Cards -->
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  <?php
  $resources = [
    ['Disk Space',    'hard-drive',   $diskUsed . ' MB', $diskLimit > 0 ? $diskLimit . ' MB' : 'Unlimited', $diskPct, 'blue'],
    ['Bandwidth',     'bar-chart-2',  $bwUsed . ' GB',   $bwLimit > 0 ? $bwLimit . ' GB' : 'Unlimited',    $bwPct,   'green'],
    ['Email Accounts','mail',         $emailCount,        'Unlimited', 0, 'indigo'],
    ['Databases',     'database',     $dbCount,           'Unlimited', 0, 'violet'],
    ['FTP Accounts',  'upload-cloud', $ftpCount,          'Unlimited', 0, 'cyan'],
    ['Subdomains',    'layers',       $subCount,          'Unlimited', 0, 'amber'],
  ];
  $colors = [
    'blue'  => ['bg-blue-50',  'text-blue-600',  'bg-blue-500'],
    'green' => ['bg-green-50', 'text-green-600', 'bg-green-500'],
    'indigo'=> ['bg-indigo-50','text-indigo-600','bg-indigo-500'],
    'violet'=> ['bg-violet-50','text-violet-600','bg-violet-500'],
    'cyan'  => ['bg-cyan-50',  'text-cyan-600',  'bg-cyan-500'],
    'amber' => ['bg-amber-50', 'text-amber-600', 'bg-amber-500'],
  ];
  foreach ($resources as [$label, $icon, $used, $limit, $pct, $color]):
    [$bg, $tc, $bar] = $colors[$color];
  ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-center justify-between mb-3">
      <div class="flex items-center gap-3">
        <div class="flex h-9 w-9 items-center justify-center rounded-lg <?= $bg ?>"><i data-lucide="<?= $icon ?>" class="h-4 w-4 <?= $tc ?>"></i></div>
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= $label ?></p>
      </div>
      <span class="text-sm font-semibold text-gray-900 dark:text-white"><?= $used ?> / <?= $limit ?></span>
    </div>
    <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700">
      <div class="h-2 rounded-full <?= $bar ?>" style="width:<?= max(3, $pct) ?>%"></div>
    </div>
    <p class="mt-1.5 text-xs text-gray-400"><?= $pct > 0 ? $pct . '% used' : 'No limit' ?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Raw usages from ResourceUsage API -->
<?php if (!empty($usages)): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
  <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Detailed Resource Limits</h2>
  <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <?php foreach ($usages as $u):
      $name  = $u['id'] ?? '';
      $used  = $u['usage'] ?? 0;
      $max   = $u['maximum'] ?? 0;
      $pct   = $max > 0 ? min(100, round($used / $max * 100)) : 0;
      if (!$name) continue;
    ?>
    <div class="rounded-lg border border-gray-100 dark:border-gray-700 p-3">
      <div class="flex justify-between text-sm mb-1">
        <span class="font-medium text-gray-700 dark:text-gray-300"><?= htmlspecialchars($name) ?></span>
        <span class="text-gray-500 dark:text-gray-400"><?= $used ?> / <?= $max > 0 ? $max : '∞' ?></span>
      </div>
      <div class="h-1.5 rounded-full bg-gray-100 dark:bg-gray-700">
        <div class="h-1.5 rounded-full bg-blue-500" style="width:<?= max(2, $pct) ?>%"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<?php include '../includes/layout_end.php'; ?>
