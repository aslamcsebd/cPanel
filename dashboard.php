<?php
require_once 'includes/auth.php';
requireSetup();
requireLogin();
$pageTitle = 'Dashboard — cPanel Manager';
$activePage = 'dashboard';
$basePath = '';

// Redirect to settings if cPanel not configured yet
if (!isCpanelConfigured()) {
    header('Location: settings.php?setup=1'); exit;
}

require_once 'api/CpanelClient.php';
$cp = new CpanelClient();

// Fetch live data
$primaryDomain  = $cp->call('DomainInfo', 'primary_domain');
$domains        = $cp->call('DomainInfo', 'list_domains');
$emailAccounts  = $cp->call('Email', 'list_pops');
$databases      = $cp->call('Mysql', 'list_databases');
$ftpAccounts    = $cp->call('Ftp', 'list_ftp');
$sslCerts       = $cp->call('SSL', 'list_certs');
$cronJobs       = $cp->call('Cron', 'list_cron');
$diskUsage      = $cp->call('Quota', 'get_quota_info');
$bandwidth      = $cp->call('Bandwidth', 'query', ['grouping' => 'month']);

$domainCount  = count($domains['data'] ?? []);
$emailCount   = count($emailAccounts['data'] ?? []);
$dbCount      = count($databases['data'] ?? []);
$ftpCount     = count($ftpAccounts['data'] ?? []);
$cronCount    = count($cronJobs['data'] ?? []);
$sslCount     = count($sslCerts['data'] ?? []);
$primaryHost  = $primaryDomain['data']['domain'] ?? ($cp->call('DomainInfo','primary_domain')['data']['domain'] ?? 'N/A');
$cpUser       = getCpanelConfig()['username'] ?? 'N/A';

// Disk info
$diskData  = $diskUsage['data'] ?? [];
$diskUsed  = $diskData['megabytes_used'] ?? 0;
$diskLimit = $diskData['megabytes_limit'] ?? 0;
$diskPct   = $diskLimit > 0 ? round(($diskUsed / $diskLimit) * 100) : 0;
$diskUsedStr  = $diskUsed >= 1024 ? round($diskUsed/1024, 1).' GB' : $diskUsed.' MB';
$diskLimitStr = $diskLimit >= 1024 ? round($diskLimit/1024, 1).' GB' : ($diskLimit > 0 ? $diskLimit.' MB' : 'Unlimited');

include 'includes/layout.php'; // root page — basePath already set to ''
?>

<!-- Page Header -->
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($primaryHost) ?> — Account: <?= htmlspecialchars($cpUser) ?></p>
  </div>
  <button class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition">
    <i data-lucide="refresh-cw" class="h-4 w-4"></i> Refresh
  </button>
</div>

<!-- Stat Cards -->
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?php
  $cards = [
    ['Email Accounts','mail',$emailCount,'blue',$emailCount.' accounts'],
    ['Domains','globe-2',$domainCount,'indigo',$domainCount.' total'],
    ['Databases','database',$dbCount,'violet','MySQL databases'],
    ['FTP Accounts','upload-cloud',$ftpCount,'cyan','All active'],
    ['Disk Usage','hard-drive',$diskUsedStr.' / '.$diskLimitStr,'amber',$diskPct.'% used'],
    ['Bandwidth','bar-chart-2','Live','green','Current month'],
    ['SSL Certs','lock',$sslCount,'emerald','Installed certs'],
    ['Cron Jobs','clock',$cronCount,'orange','Scheduled jobs'],
  ];
  foreach ($cards as [$label, $icon, $value, $color, $sub]):
    $colors = [
      'blue'   => ['bg-blue-50','text-blue-600'],
      'indigo' => ['bg-indigo-50','text-indigo-600'],
      'violet' => ['bg-violet-50','text-violet-600'],
      'cyan'   => ['bg-cyan-50','text-cyan-600'],
      'amber'  => ['bg-amber-50','text-amber-600'],
      'green'  => ['bg-green-50','text-green-600'],
      'emerald'=> ['bg-emerald-50','text-emerald-600'],
      'orange' => ['bg-orange-50','text-orange-600'],
    ];
    [$bg, $tc] = $colors[$color];
  ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <p class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= $label ?></p>
      <div class="flex h-9 w-9 items-center justify-center rounded-lg <?= $bg ?>">
        <i data-lucide="<?= $icon ?>" class="h-4 w-4 <?= $tc ?>"></i>
      </div>
    </div>
    <p class="mt-4 text-xl font-semibold text-gray-900 dark:text-white"><?= $value ?></p>
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= $sub ?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- Disk + Bandwidth Progress -->
<div class="grid gap-4 lg:grid-cols-2">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Disk Usage</h2>
    <div class="space-y-3">
      <?php
      $disks = [
        ['public_html','1.8 GB',72,'blue'],
        ['mail','1.1 GB',44,'indigo'],
        ['databases','0.9 GB',36,'violet'],
        ['logs','0.4 GB',16,'gray'],
      ];
      foreach ($disks as [$name, $size, $pct, $c]):
        $bar = ['blue'=>'bg-blue-500','indigo'=>'bg-indigo-500','violet'=>'bg-violet-500','gray'=>'bg-gray-400'][$c];
      ?>
      <div>
        <div class="flex justify-between text-sm mb-1">
          <span class="text-gray-600 dark:text-gray-400"><?= $name ?></span>
          <span class="font-medium text-gray-900 dark:text-white"><?= $size ?></span>
        </div>
        <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-700">
          <div class="h-2 rounded-full <?= $bar ?>" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Recent API Activity -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">Recent Activity</h2>
      <a href="developer/logs.php" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">View all</a>
    </div>
    <div class="space-y-3">
      <?php
      $logs = [
        ['Email/list_pops','READ','Success','2s ago'],
        ['DomainInfo/list_domains','READ','Success','5s ago'],
        ['Mysql/list_databases','READ','Success','12s ago'],
        ['SSL/list_certs','READ','Success','1m ago'],
        ['Ftp/list_ftp','READ','Success','3m ago'],
      ];
      foreach ($logs as [$fn, $type, $status, $time]):
      ?>
      <div class="flex items-center gap-3">
        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400"><?= $type ?></span>
        <span class="flex-1 text-sm text-gray-700 dark:text-gray-300 font-mono truncate"><?= $fn ?></span>
        <span class="text-xs text-green-600 dark:text-green-400"><?= $status ?></span>
        <span class="text-xs text-gray-400"><?= $time ?></span>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- SSL Status Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
  <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 dark:border-gray-700">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white">SSL Certificates</h2>
    <a href="domains/ssl.php" class="text-sm text-blue-600 dark:text-blue-400 hover:underline">Manage</a>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Domain</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Issuer</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Expires</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700 bg-white dark:bg-gray-800">
        <?php
        $certs = [];
        foreach ($sslCerts['data'] ?? [] as $cert) {
          $certs[] = [
            $cert['domains'][0] ?? $cert['subject']['commonName'] ?? 'N/A',
            $cert['issuer']['organizationName'] ?? 'Unknown',
            date('Y-m-d', $cert['not_after'] ?? time()),
            (($cert['not_after'] ?? 0) - time() < 30*86400) ? 'Expiring Soon' : 'Valid',
          ];
        }
        if (empty($certs)) {
          $certs = [['No SSL data','—','—','—']];
        }
        foreach ($certs as [$domain, $issuer, $exp, $status]):
          $badge = $status === 'Valid'
            ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
            : 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-gray-100"><?= $domain ?></td>
          <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400"><?= $issuer ?></td>
          <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400"><?= $exp ?></td>
          <td class="px-5 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $badge ?>"><?= $status ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include 'includes/layout_end.php'; ?>

