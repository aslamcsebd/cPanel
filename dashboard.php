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

$domainCount  = count($domains['data'] ?? []);
$emailCount   = count($emailAccounts['data'] ?? []);
$dbCount      = count($databases['data'] ?? []);
$ftpCount     = count($ftpAccounts['data'] ?? []);
$cronCount    = count($cronJobs['data'] ?? []);
$sslCount     = count($sslCerts['data'] ?? []);
$primaryHost  = $primaryDomain['data']['domain'] ?? ($cp->call('DomainInfo','primary_domain')['data']['domain'] ?? 'N/A');
$cpUser       = getCpanelConfig()['username'] ?? 'N/A';

include 'includes/layout.php'; // root page — basePath already set to ''
?>

<!-- Page Header -->
<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Dashboard</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($primaryHost) ?> — Account: <?= htmlspecialchars($cpUser) ?></p>
  </div>
  <button onclick="location.reload()" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition">
    <i data-lucide="refresh-cw" class="h-4 w-4"></i> Refresh
  </button>
</div>

<!-- Stat Cards -->
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?php
  $cards = [
    ['Email Accounts','mail',$emailCount,'blue','email/accounts.php'],
    ['Domains','globe-2',$domainCount,'indigo','domains/index.php'],
    ['Databases','database',$dbCount,'violet','databases/mysql.php'],
    ['FTP Accounts','upload-cloud',$ftpCount,'cyan','services/ftp.php'],
    ['SSL Certs','lock',$sslCount,'emerald','domains/ssl.php'],
    ['Cron Jobs','clock',$cronCount,'orange','services/cron.php'],
  ];
  foreach ($cards as [$label, $icon, $value, $color, $href]):
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
  <a href="<?= htmlspecialchars($href) ?>" class="block rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-blue-400 dark:hover:border-blue-500 transition h-full">
    <div class="flex items-center justify-between h-full">
      <div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400"><?= $label ?></p>
        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= $value ?></p>
      </div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg <?= $bg ?>">
        <i data-lucide="<?= $icon ?>" class="h-6 w-6 <?= $tc ?>"></i>
      </div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<?php include 'includes/layout_end.php'; ?>

