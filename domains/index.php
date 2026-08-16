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
            'newdomain'  => trim($_POST['domain'] ?? ''),
            'subdomain'  => trim($_POST['subdomain'] ?? ''),
            'dir'        => trim($_POST['dir'] ?? ''),
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

$sslResult = $client->call('SSL', 'list_certs');
$certDomains = array_column($sslResult['data'] ?? [], 'domains');
$certDomains = array_merge(...array_map(fn($d) => (array)$d, $certDomains));

$pageTitle  = 'Domains — cPanel Manager';
$activePage = 'domains';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Domains</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: DomainInfo/list_domains</p>
  </div>
  <button onclick="document.getElementById('modal-add').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Add Domain
  </button>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Stats -->
<div class="grid gap-4 sm:grid-cols-4">
  <?php foreach ([['Primary','1','globe-2','blue'],['Addon',count($addons),'plus-circle','indigo'],['Subdomains',count($subs),'layers','violet']] as [$l,$v,$i,$c]):
    $colors = ['blue'=>['bg-blue-50','text-blue-600'],'indigo'=>['bg-indigo-50','text-indigo-600'],'violet'=>['bg-violet-50','text-violet-600']];
    [$bg,$tc] = $colors[$c];
  ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg <?= $bg ?>"><i data-lucide="<?= $i ?>" class="h-5 w-5 <?= $tc ?>"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400"><?= $l ?></p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= $v ?></p></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Domain</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">SSL</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php
        $allDomains = [];
        if ($primary) $allDomains[] = ['domain' => $primary, 'type' => 'Primary'];
        foreach ($addons as $d) $allDomains[] = ['domain' => $d, 'type' => 'Addon'];
        foreach ($subs as $d) $allDomains[] = ['domain' => $d, 'type' => 'Subdomain'];
        if (empty($allDomains)): ?>
        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">No domains found.</td></tr>
        <?php endif; ?>
        <?php foreach ($allDomains as $row):
          $domain = $row['domain'];
          $type   = $row['type'];
          $hasSSL = in_array($domain, $certDomains);
          $typeBadge = match($type) {
            'Primary' => 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
            'Addon'   => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400',
            default   => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
          };
          $sslBadge = $hasSSL ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($domain) ?></td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $typeBadge ?>"><?= $type ?></span></td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $sslBadge ?>"><?= $hasSSL ? 'SSL' : 'No SSL' ?></span></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <a href="dns.php?domain=<?= urlencode($domain) ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="DNS"><i data-lucide="network" class="h-4 w-4"></i></a>
              <?php if ($type !== 'Primary'): ?>
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="domain" value="<?= htmlspecialchars($domain) ?>" />
                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
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
        <input type="text" name="domain" placeholder="newdomain.com" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Subdomain (auto-created)</label>
        <input type="text" name="subdomain" placeholder="newdomain" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Document Root</label>
        <input type="text" name="dir" placeholder="/public_html/newdomain" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal-add').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add Domain</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
