<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

$domainsResult = $client->call('DomainInfo', 'list_domains');
$allDomains = array_values(array_filter(array_merge(
    [$domainsResult['data']['main_domain'] ?? ''],
    $domainsResult['data']['addon_domains'] ?? []
)));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $domain = trim($_POST['domain'] ?? '');

    if ($action === 'apply') {
        $params = [
            'domain' => $domain,
            'p'      => $_POST['p'] ?? 'none',
            'pct'    => (int)($_POST['pct'] ?? 100),
        ];
        if (!empty($_POST['sp']))  $params['sp']  = $_POST['sp'];
        if (!empty($_POST['rua'])) $params['rua'] = 'mailto:' . trim($_POST['rua']);
        if (!empty($_POST['ruf'])) $params['ruf'] = 'mailto:' . trim($_POST['ruf']);
        $r = $client->call('EmailAuth', 'apply_dmarc', $params);
        $r['success'] ? $msg = "DMARC policy applied for {$domain}." : $err = $r['errors'][0] ?? 'Failed.';

    } elseif ($action === 'remove') {
        $r = $client->call('EmailAuth', 'remove_dmarc', ['domain' => $domain]);
        $r['success'] ? $msg = "DMARC removed for {$domain}." : $err = $r['errors'][0] ?? 'Failed.';
    }
}

// Fetch DMARC record for each domain
$dmarcRecords = [];
foreach ($allDomains as $d) {
    $r = $client->call('EmailAuth', 'fetch_dmarc', ['domain' => $d]);
    $dmarcRecords[$d] = $r['data'] ?? null;
}

$pageTitle  = 'DMARC — cPanel Manager';
$activePage = 'email-dmarc';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">DMARC</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Domain-based Message Authentication, Reporting & Conformance</p>
  </div>
</div>

<?php if ($msg): ?>
<div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2">
  <i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>
<?php if ($err): ?>
<div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2">
  <i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 flex items-start gap-3">
  <i data-lucide="info" class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"></i>
  <p class="text-sm text-blue-700 dark:text-blue-400">DMARC tells receiving servers what to do when an email fails SPF or DKIM checks. Requires SPF and DKIM to be configured first.</p>
</div>

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Domain</th>
          <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Policy</th>
          <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Subdomain Policy</th>
          <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">Report Email</th>
          <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400">% Checked</th>
          <th class="px-5 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($allDomains)): ?>
        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400">No domains found.</td></tr>
        <?php endif; ?>
        <?php
        $policyColors = [
            'none'       => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
            'quarantine' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',
            'reject'     => 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400',
        ];
        foreach ($allDomains as $domain):
            $rec    = $dmarcRecords[$domain] ?? null;
            $policy = $rec['p']   ?? null;
            $sp     = $rec['sp']  ?? '—';
            $rua    = isset($rec['rua']) ? str_replace('mailto:', '', $rec['rua']) : '—';
            $pct    = $rec['pct'] ?? '—';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($domain) ?></td>
          <td class="px-5 py-3.5">
            <?php if ($policy): ?>
            <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $policyColors[$policy] ?? 'bg-gray-100 text-gray-500' ?>"><?= htmlspecialchars($policy) ?></span>
            <?php else: ?>
            <span class="text-xs text-gray-400">Not set</span>
            <?php endif; ?>
          </td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($sp) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($rua) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $pct !== '—' ? htmlspecialchars($pct) . '%' : '—' ?></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <button onclick="openModal('<?= htmlspecialchars($domain) ?>',<?= json_encode($rec) ?>)"
                class="inline-flex items-center gap-1.5 h-8 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white hover:bg-blue-700">
                <?= $policy ? 'Edit' : 'Add Policy' ?>
              </button>
              <?php if ($policy): ?>
              <form method="POST" onsubmit="return confirm('Remove DMARC for <?= htmlspecialchars($domain) ?>?')" class="inline">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="domain" value="<?= htmlspecialchars($domain) ?>">
                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                  <i data-lucide="trash-2" class="h-4 w-4"></i>
                </button>
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

<!-- Add/Edit DMARC Modal -->
<div id="modal-dmarc" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
  <div class="w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 id="modal-title" class="text-base font-semibold text-gray-900 dark:text-white">Add DMARC Policy</h3>
      <button onclick="document.getElementById('modal-dmarc').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="apply">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
        <input type="text" name="domain" id="modal-domain" readonly class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 text-sm text-gray-500 outline-none cursor-not-allowed">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Policy (p=)</label>
          <select name="p" id="modal-p" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
            <option value="none">none — Monitor only</option>
            <option value="quarantine">quarantine — Send to spam</option>
            <option value="reject">reject — Block message</option>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Subdomain Policy (sp=)</label>
          <select name="sp" id="modal-sp" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
            <option value="">Same as policy</option>
            <option value="none">none</option>
            <option value="quarantine">quarantine</option>
            <option value="reject">reject</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Report Email (rua=)</label>
          <input type="email" name="rua" id="modal-rua" placeholder="dmarc@yourdomain.com" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">% of messages (pct=)</label>
          <input type="number" name="pct" id="modal-pct" value="100" min="1" max="100" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal-dmarc').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply Policy</button>
      </div>
    </form>
  </div>
</div>

<script>
function openModal(domain, rec) {
    document.getElementById('modal-domain').value = domain;
    document.getElementById('modal-title').textContent = rec ? 'Edit DMARC Policy' : 'Add DMARC Policy';
    document.getElementById('modal-p').value   = rec?.p   || 'none';
    document.getElementById('modal-sp').value  = rec?.sp  || '';
    document.getElementById('modal-rua').value = rec?.rua ? rec.rua.replace('mailto:', '') : '';
    document.getElementById('modal-pct').value = rec?.pct || 100;
    document.getElementById('modal-dmarc').classList.remove('hidden');
}
</script>

<?php include '../includes/layout_end.php'; ?>
