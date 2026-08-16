<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

$domainsResult = $client->call('DomainInfo', 'list_domains');
$domains = array_filter(array_merge(
    [$domainsResult['data']['main_domain'] ?? ''],
    $domainsResult['data']['addon_domains'] ?? []
));
$selectedDomain = $_GET['domain'] ?? (reset($domains) ?: '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $zone   = $_POST['zone'] ?? $selectedDomain;
    if ($action === 'add') {
        $r = $client->call('DNS', 'mass_edit_zone', [
            'zone'       => $zone,
            'add'        => json_encode([[
                'dname' => trim($_POST['name'] ?? ''),
                'ttl'   => (int)($_POST['ttl'] ?? 14400),
                'type'  => $_POST['type'] ?? 'A',
                'data'  => [trim($_POST['value'] ?? '')],
            ]]),
        ]);
        $r['success'] ? $msg = 'DNS record added.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete') {
        $r = $client->call('DNS', 'mass_edit_zone', [
            'zone'   => $zone,
            'remove' => json_encode([(int)$_POST['line_index']]),
        ]);
        $r['success'] ? $msg = 'DNS record deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    }
    $selectedDomain = $zone;
}

$zoneResult = $client->call('DNS', 'parse_zone', ['zone' => $selectedDomain]);
$records    = array_filter($zoneResult['data'] ?? [], fn($r) => !empty($r['type']) && $r['type'] !== 'SOA');

$typeColors = [
    'A'     => 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
    'AAAA'  => 'bg-indigo-50 text-indigo-700',
    'CNAME' => 'bg-violet-50 text-violet-700 dark:bg-violet-900/20 dark:text-violet-400',
    'MX'    => 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',
    'TXT'   => 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400',
    'NS'    => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
];

$pageTitle  = 'DNS Management — cPanel Manager';
$activePage = 'dns';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">DNS Management</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: DNS/parse_zone, DNS/mass_edit_zone</p>
  </div>
  <button onclick="document.getElementById('modal-add').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Add Record
  </button>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="flex items-center gap-3">
  <form method="GET">
    <label class="text-sm font-medium text-gray-700 dark:text-gray-300 mr-2">Zone:</label>
    <select name="domain" onchange="this.form.submit()" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
      <?php foreach ($domains as $d): ?>
      <option value="<?= htmlspecialchars($d) ?>" <?= $d === $selectedDomain ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">TTL</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Value</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($records)): ?>
        <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-400">No DNS records found.</td></tr>
        <?php endif; ?>
        <?php foreach ($records as $rec):
          $type  = $rec['type'] ?? '';
          $name  = rtrim($rec['dname'] ?? '', '.');
          $ttl   = $rec['ttl'] ?? '';
          $data  = implode(' ', (array)($rec['data'] ?? []));
          $badge = $typeColors[$type] ?? 'bg-gray-100 text-gray-700';
          $lineIndex = $rec['line_index'] ?? null;
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3 text-sm font-mono font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($name) ?></td>
          <td class="px-5 py-3"><span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold <?= $badge ?>"><?= $type ?></span></td>
          <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400"><?= $ttl ?></td>
          <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300 font-mono max-w-xs truncate"><?= htmlspecialchars($data) ?></td>
          <td class="px-5 py-3 text-right">
            <?php if ($lineIndex !== null && !in_array($type, ['NS', 'SOA'])): ?>
            <form method="POST" class="inline">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="zone" value="<?= htmlspecialchars($selectedDomain) ?>" />
              <input type="hidden" name="line_index" value="<?= $lineIndex ?>" />
              <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div id="modal-add" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add DNS Record</h3>
      <button onclick="document.getElementById('modal-add').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 grid gap-4 sm:grid-cols-2">
      <input type="hidden" name="action" value="add" />
      <input type="hidden" name="zone" value="<?= htmlspecialchars($selectedDomain) ?>" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
        <select name="type" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
          <option>A</option><option>AAAA</option><option>CNAME</option><option>MX</option><option>TXT</option><option>SRV</option>
        </select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
        <input type="text" name="name" placeholder="@ or subdomain" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">TTL</label>
        <input type="number" name="ttl" value="14400" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Value</label>
        <input type="text" name="value" placeholder="IP or value" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div class="sm:col-span-2 flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('modal-add').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add Record</button>
      </div>
    </form>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
