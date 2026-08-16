<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $r = $client->call('SSL', 'delete_ssl', ['domain' => $_POST['domain'] ?? '']);
        $r['success'] ? $msg = 'Certificate removed.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$result = $client->call('SSL', 'list_certs');
$certs  = $result['data'] ?? [];

$valid   = 0; $expiring = 0; $nossl = 0;
$now     = time();
foreach ($certs as $c) {
    $exp = strtotime($c['not_after'] ?? '');
    $days = ($exp - $now) / 86400;
    if ($days < 0) $nossl++;
    elseif ($days < 30) $expiring++;
    else $valid++;
}

$pageTitle  = 'SSL / TLS — cPanel Manager';
$activePage = 'ssl';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">SSL / TLS</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: SSL/list_certs</p>
  </div>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="grid gap-4 sm:grid-cols-3">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50"><i data-lucide="shield-check" class="h-5 w-5 text-green-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Valid</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= $valid ?></p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50"><i data-lucide="alert-triangle" class="h-5 w-5 text-amber-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Expiring Soon</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= $expiring ?></p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-gray-100"><i data-lucide="lock" class="h-5 w-5 text-gray-500"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Total Certs</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= count($certs) ?></p></div>
  </div>
</div>

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
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
        <?php if (empty($certs)): ?>
        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">No certificates found.</td></tr>
        <?php endif; ?>
        <?php foreach ($certs as $c):
          $domain  = $c['subject']['commonName'] ?? ($c['domains'][0] ?? 'N/A');
          $issuer  = $c['issuer']['organizationName'] ?? 'Unknown';
          $expDate = $c['not_after'] ?? '';
          $exp     = strtotime($expDate);
          $days    = (int)(($exp - $now) / 86400);
          if ($days < 0) { $status = 'Expired'; $badge = 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400'; }
          elseif ($days < 30) { $status = 'Expiring Soon'; $badge = 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400'; }
          else { $status = 'Valid'; $badge = 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'; }
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($domain) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($issuer) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $expDate ? date('Y-m-d', $exp) . " ({$days}d)" : '—' ?></td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $badge ?>"><?= $status ?></span></td>
          <td class="px-5 py-3.5 text-right">
            <form method="POST" class="inline">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="domain" value="<?= htmlspecialchars($domain) ?>" />
              <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50" title="Remove"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
