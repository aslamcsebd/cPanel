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
    $domainsResult['data']['addon_domains'] ?? []
)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $zone   = $_POST['zone'] ?? '';
    if ($action === 'enable') {
        $r = $client->call('DNSSEC', 'enable_dnssec', ['zone' => $zone]);
        $r['success'] ? $msg = "DNSSEC enabled for {$zone}." : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'disable') {
        $r = $client->call('DNSSEC', 'disable_dnssec', ['zone' => $zone]);
        $r['success'] ? $msg = "DNSSEC disabled for {$zone}." : $err = $r['errors'][0] ?? 'Failed.';
    }
}

// Fetch DNSSEC status for each domain
$zoneStatuses = [];
foreach ($domains as $domain) {
    $r = $client->call('DNSSEC', 'fetch_ds_records', ['zone' => $domain]);
    $dsRecords = $r['data'] ?? [];
    $zoneStatuses[$domain] = [
        'enabled'   => !empty($dsRecords),
        'ds_records' => $dsRecords,
    ];
}

$enabled  = count(array_filter($zoneStatuses, fn($z) => $z['enabled']));
$disabled = count($zoneStatuses) - $enabled;

$pageTitle  = 'DNSSEC — cPanel Manager';
$activePage = 'dnssec';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">DNSSEC</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: DNSSEC/enable_dnssec · DNSSEC/disable_dnssec · DNSSEC/fetch_ds_records</p>
  </div>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Stats -->
<div class="grid gap-4 sm:grid-cols-3">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20"><i data-lucide="globe-2" class="h-5 w-5 text-blue-600 dark:text-blue-400"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Total Zones</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= count($domains) ?></p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20"><i data-lucide="shield-check" class="h-5 w-5 text-green-600 dark:text-green-400"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">DNSSEC Enabled</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= $enabled ?></p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100 dark:bg-gray-700"><i data-lucide="shield-off" class="h-5 w-5 text-gray-500 dark:text-gray-400"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Not Secured</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= $disabled ?></p></div>
  </div>
</div>

<!-- Zone Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">DNS Zones</h2>
  </div>
  <div class="divide-y divide-gray-100 dark:divide-gray-700">
    <?php if (empty($domains)): ?>
    <div class="px-5 py-10 text-center text-sm text-gray-400">No domains found.</div>
    <?php endif; ?>
    <?php foreach ($domains as $domain):
      $status    = $zoneStatuses[$domain];
      $isEnabled = $status['enabled'];
      $dsRecords = $status['ds_records'];
    ?>
    <div class="px-5 py-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="flex h-9 w-9 items-center justify-center rounded-lg <?= $isEnabled ? 'bg-green-50 dark:bg-green-900/20' : 'bg-gray-100 dark:bg-gray-700' ?>">
            <i data-lucide="<?= $isEnabled ? 'shield-check' : 'shield-off' ?>" class="h-4 w-4 <?= $isEnabled ? 'text-green-600 dark:text-green-400' : 'text-gray-400' ?>"></i>
          </div>
          <div>
            <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($domain) ?></p>
            <p class="text-xs <?= $isEnabled ? 'text-green-600 dark:text-green-400' : 'text-gray-400' ?>">
              <?= $isEnabled ? 'DNSSEC Active — ' . count($dsRecords) . ' DS record(s)' : 'DNSSEC Disabled' ?>
            </p>
          </div>
        </div>
        <div class="flex items-center gap-2">
          <?php if ($isEnabled): ?>
            <button onclick="toggleDS('ds-<?= md5($domain) ?>')"
              class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50">
              <i data-lucide="key" class="h-3.5 w-3.5"></i> DS Records
            </button>
            <form method="POST" onsubmit="return confirm('Disable DNSSEC for <?= htmlspecialchars($domain) ?>?')">
              <input type="hidden" name="action" value="disable" />
              <input type="hidden" name="zone" value="<?= htmlspecialchars($domain) ?>" />
              <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 dark:border-red-800 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20">
                <i data-lucide="shield-off" class="h-3.5 w-3.5"></i> Disable
              </button>
            </form>
          <?php else: ?>
            <form method="POST">
              <input type="hidden" name="action" value="enable" />
              <input type="hidden" name="zone" value="<?= htmlspecialchars($domain) ?>" />
              <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
                <i data-lucide="shield-check" class="h-3.5 w-3.5"></i> Enable DNSSEC
              </button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($isEnabled && !empty($dsRecords)): ?>
      <div id="ds-<?= md5($domain) ?>" class="hidden mt-3 rounded-lg border border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50 overflow-hidden">
        <table class="min-w-full text-xs">
          <thead>
            <tr class="border-b border-gray-200 dark:border-gray-600">
              <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Key Tag</th>
              <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Algorithm</th>
              <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Digest Type</th>
              <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-400">Digest</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
            <?php foreach ($dsRecords as $ds): ?>
            <tr>
              <td class="px-4 py-2 font-mono text-gray-700 dark:text-gray-300"><?= htmlspecialchars($ds['key_tag'] ?? '—') ?></td>
              <td class="px-4 py-2 text-gray-600 dark:text-gray-400"><?= htmlspecialchars($ds['algorithm'] ?? '—') ?></td>
              <td class="px-4 py-2 text-gray-600 dark:text-gray-400"><?= htmlspecialchars($ds['digest_type'] ?? '—') ?></td>
              <td class="px-4 py-2 font-mono text-gray-500 dark:text-gray-400 max-w-xs truncate" title="<?= htmlspecialchars($ds['digest'] ?? '') ?>"><?= htmlspecialchars($ds['digest'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
function toggleDS(id) {
  document.getElementById(id).classList.toggle('hidden');
}
</script>

<?php include '../includes/layout_end.php'; ?>
