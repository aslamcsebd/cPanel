<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $domain = $_POST['domain'] ?? '';
    if ($action === 'enable') {
        $r = $client->call('EmailAuth', 'enable_dkim', ['domain' => $domain]);
        $r['success'] ? $msg = "DKIM enabled for $domain." : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'disable') {
        $r = $client->call('EmailAuth', 'disable_dkim', ['domain' => $domain]);
        $r['success'] ? $msg = "DKIM disabled for $domain." : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$domainsResult = $client->call('DomainInfo', 'list_domains');
$allDomains = array_filter(array_merge(
    [$domainsResult['data']['main_domain'] ?? ''],
    $domainsResult['data']['addon_domains'] ?? []
));

// Fetch DKIM status for each domain
$dkimStatuses = [];
foreach ($allDomains as $d) {
    $r = $client->call('EmailAuth', 'fetch_dkim_private_keys', ['domain' => $d]);
    $dkimStatuses[$d] = !empty($r['data']);
}

$pageTitle  = 'DKIM — cPanel Manager';
$activePage = 'email-dkim';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">DKIM</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: EmailAuth/enable_dkim, EmailAuth/disable_dkim</p>
  </div>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 flex items-start gap-3">
  <i data-lucide="info" class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"></i>
  <p class="text-sm text-blue-700 dark:text-blue-400">DKIM adds a digital signature to outgoing emails, helping receiving servers verify the message was sent from your domain.</p>
</div>

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Domain</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($allDomains)): ?>
        <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400">No domains found.</td></tr>
        <?php endif; ?>
        <?php foreach ($allDomains as $domain):
          $enabled = $dkimStatuses[$domain] ?? false;
          $badge   = $enabled ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($domain) ?></td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $badge ?>"><?= $enabled ? 'Enabled' : 'Disabled' ?></span></td>
          <td class="px-5 py-3.5 text-right">
            <form method="POST" class="inline">
              <input type="hidden" name="domain" value="<?= htmlspecialchars($domain) ?>" />
              <?php if ($enabled): ?>
              <input type="hidden" name="action" value="disable" />
              <button type="submit" class="inline-flex items-center gap-1.5 h-8 rounded-lg border border-red-200 bg-red-50 px-3 text-xs font-medium text-red-600 hover:bg-red-100">Disable</button>
              <?php else: ?>
              <input type="hidden" name="action" value="enable" />
              <button type="submit" class="inline-flex items-center gap-1.5 h-8 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white hover:bg-blue-700">Enable</button>
              <?php endif; ?>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
