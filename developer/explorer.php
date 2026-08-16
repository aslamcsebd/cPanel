<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$response = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['module']) && !empty($_POST['function'])) {
    $client = new CpanelClient();
    $params = [];
    if (!empty($_POST['params'])) {
        $params = json_decode($_POST['params'], true) ?? [];
    }
    $response = $client->call($_POST['module'], $_POST['function'], $params);
}

$pageTitle  = 'API Explorer — cPanel Manager';
$activePage = 'api-explorer';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">API Explorer</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Browse and test cPanel UAPI endpoints</p>
  </div>
  <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 dark:bg-blue-900/20 px-3 py-1.5 text-xs font-medium text-blue-700 dark:text-blue-400">
    <i data-lucide="info" class="h-3.5 w-3.5"></i> Requests go through backend only
  </span>
</div>

<div class="grid gap-4 lg:grid-cols-3">
  <!-- Selector -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-4 space-y-4">
    <form method="POST" id="explorer-form">
      <div class="space-y-4">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Module</label>
          <select name="module" id="api-module" onchange="updateFunctions()" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
            <option value="">Select module...</option>
            <?php foreach (['Email','DomainInfo','DNS','Mysql','Ftp','SSL','Cron','Bandwidth','Tokens','ResourceUsage','VersionControl','DNSSEC','SubDomain','WebDisk','Features','Quota','EmailAuth','AddonDomain'] as $m): ?>
            <option value="<?= $m ?>" <?= ($_POST['module'] ?? '') === $m ? 'selected' : '' ?>><?= $m ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Function</label>
          <select name="function" id="api-function" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
            <option value="">Select function...</option>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Parameters (JSON)</label>
          <textarea name="params" id="api-params" rows="4" placeholder='{"domain": "example.com"}' class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm font-mono outline-none focus:border-blue-400 resize-none"><?= htmlspecialchars($_POST['params'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 flex items-center justify-center gap-2">
          <i data-lucide="play" class="h-4 w-4"></i> Execute Request
        </button>
      </div>
    </form>
  </div>

  <!-- Response -->
  <div class="lg:col-span-2 space-y-4">
    <?php if ($response !== null): ?>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-4">
      <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-3">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Response</h3>
          <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $response['success'] ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400' ?>">
            <?= $response['success'] ? 'Success' : 'Failed' ?>
          </span>
          <span class="text-xs text-gray-400"><?= htmlspecialchars($response['duration'] ?? '') ?></span>
        </div>
        <button onclick="navigator.clipboard?.writeText(document.getElementById('response-body').textContent)" class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 dark:text-gray-400">
          <i data-lucide="copy" class="h-3.5 w-3.5"></i> Copy
        </button>
      </div>
      <?php if (!empty($response['errors'])): ?>
      <div class="mb-3 rounded-lg bg-red-50 dark:bg-red-900/20 px-3 py-2 text-sm text-red-700 dark:text-red-400">
        <?= htmlspecialchars(implode(', ', $response['errors'])) ?>
      </div>
      <?php endif; ?>
      <pre id="response-body" class="text-xs font-mono text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-700/50 rounded-lg p-3 overflow-x-auto max-h-[500px]"><?= htmlspecialchars(json_encode($response['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
    </div>
    <?php else: ?>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-10 text-center">
      <i data-lucide="terminal" class="h-10 w-10 text-gray-300 mx-auto mb-3"></i>
      <p class="text-sm text-gray-500 dark:text-gray-400">Select a module and function, then click Execute.</p>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const apiFunctions = {
  Email: ['list_pops','count_pops','add_pop','delete_pop','passwd_pop','list_forwarders','add_forwarder','delete_forwarder','list_auto_responders','add_auto_responder','delete_auto_responder','list_mxs','add_mx','delete_mx','set_mx','list_filters'],
  DomainInfo: ['list_domains','primary_domain','domains_data'],
  DNS: ['parse_zone','mass_edit_zone','has_local_authority','lookup'],
  Mysql: ['list_databases','create_database','delete_database','list_users','create_user','delete_user','get_server_information','set_privileges_on_database'],
  Ftp: ['list_ftp','list_ftp_with_disk','add_ftp','delete_ftp','passwd'],
  SSL: ['list_certs','install_ssl','delete_ssl'],
  Cron: ['list_cron','add_cron','remove_cron'],
  Bandwidth: ['query'],
  Tokens: ['list','create_full_access','revoke','rename'],
  ResourceUsage: ['get_usages'],
  VersionControl: ['retrieve','create','update','delete'],
  DNSSEC: ['enable_dnssec','disable_dnssec','fetch_ds_records'],
  SubDomain: ['addsubdomain','delsubdomain'],
  WebDisk: ['list_accounts','create_user','delete_user'],
  Features: ['list_features'],
  Quota: ['get_quota_info'],
  EmailAuth: ['enable_dkim','disable_dkim','fetch_dkim_private_keys','apply_dmarc','remove_dmarc'],
  AddonDomain: ['addaddondomain','deladdondomain'],
};

function updateFunctions() {
  const mod = document.getElementById('api-module').value;
  const sel = document.getElementById('api-function');
  const current = '<?= htmlspecialchars($_POST['function'] ?? '') ?>';
  sel.innerHTML = '<option value="">Select function...</option>';
  (apiFunctions[mod] || []).forEach(f => {
    sel.innerHTML += `<option value="${f}" ${f === current ? 'selected' : ''}>${f}</option>`;
  });
}
updateFunctions();
</script>

<?php include '../includes/layout_end.php'; ?>
