<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $r = $client->call('Email', 'add_pop', [
            'email'    => trim($_POST['email'] ?? ''),
            'domain'   => trim($_POST['domain'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'quota'    => (int)($_POST['quota'] ?? 0),
        ]);
        if ($r['success']) {
            // Save password to config so mailbox works without reset
            $fullEmail = trim($_POST['email'] ?? '') . '@' . trim($_POST['domain'] ?? '');
            $config = readConfig();
            $config['email_passwords'][$fullEmail] = $_POST['password'];
            writeConfig($config);
            $msg = 'Email account created.';
        } else {
            $err = $r['errors'][0] ?? 'Failed.';
        }
    } elseif ($action === 'delete') {
        $r = $client->call('Email', 'delete_pop', ['email' => $_POST['email'] ?? '']);
        $r['success'] ? $msg = 'Account deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'passwd') {
        $r = $client->call('Email', 'passwd_pop', [
            'email'    => $_POST['email'] ?? '',
            'password' => $_POST['password'] ?? '',
        ]);
        if ($r['success']) {
            // Update saved password in config
            $config = readConfig();
            $config['email_passwords'][$_POST['email']] = $_POST['password'];
            writeConfig($config);
            $msg = 'Password updated.';
        } else {
            $err = $r['errors'][0] ?? 'Failed.';
        }
    }
}

$result   = $client->call('Email', 'list_pops');
$accounts = $result['data'] ?? [];

$domainsResult = $client->call('DomainInfo', 'list_domains');
$domains = array_merge(
    [$domainsResult['data']['main_domain'] ?? ''],
    $domainsResult['data']['addon_domains'] ?? [],
    $domainsResult['data']['sub_domains'] ?? []
);
$domains = array_filter($domains);

$pageTitle  = 'Email Accounts — cPanel Manager';
$activePage = 'email-accounts';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Email Accounts</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Email/list_pops</p>
  </div>
  <button onclick="document.getElementById('modal-create-email').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Create Account
  </button>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Stats -->
<div class="grid gap-4 sm:grid-cols-3">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50"><i data-lucide="mail" class="h-5 w-5 text-blue-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Total Accounts</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= count($accounts) ?></p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50"><i data-lucide="hard-drive" class="h-5 w-5 text-green-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Total Usage</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= round(array_sum(array_column($accounts, 'diskused')) / 1024, 1) ?> MB</p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50"><i data-lucide="globe-2" class="h-5 w-5 text-indigo-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Domains</p><p class="text-xl font-semibold text-gray-900 dark:text-white"><?= count($domains) ?></p></div>
  </div>
</div>

<!-- Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Email</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Quota</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Usage</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($accounts)): ?>
        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">No email accounts found.</td></tr>
        <?php endif; ?>
        <?php foreach ($accounts as $acc):
          $email  = $acc['email'] ?? ($acc['login'] . '@' . $acc['domain']);
          $quota  = ($acc['quota'] ?? 0) == 0 ? 'Unlimited' : $acc['quota'] . ' MB';
          $used   = round(($acc['diskused'] ?? 0) / 1024, 1) . ' MB';
          $letter = strtoupper($email[0]);
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5">
            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 text-xs font-semibold"><?= $letter ?></div>
              <span class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($email) ?></span>
            </div>
          </td>
          <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300"><?= $quota ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300"><?= $used ?></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <a href="webmail_sso.php?email=<?= urlencode($email) ?>" target="_blank" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-blue-500 hover:bg-blue-50" title="Open Webmail"><i data-lucide="external-link" class="h-4 w-4"></i></a>
              <a href="mailbox.php?account=<?= urlencode($email) ?>" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="View Mailbox"><i data-lucide="inbox" class="h-4 w-4"></i></a>
              <button onclick="openPasswd('<?= htmlspecialchars($email) ?>')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="Change Password"><i data-lucide="key" class="h-4 w-4"></i></button>
              <button onclick="confirmDelete('<?= htmlspecialchars($email) ?>')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Modal -->
<div id="modal-create-email" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Create Email Account</h3>
      <button onclick="document.getElementById('modal-create-email').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="create" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
        <input type="text" name="email" placeholder="username" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
        <select name="domain" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none">
          <?php foreach ($domains as $d): ?><option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
        <input type="password" name="password" placeholder="Strong password" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quota MB (0 = unlimited)</label>
        <input type="number" name="quota" value="0" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex justify-end gap-2 pt-2">
        <button type="button" onclick="document.getElementById('modal-create-email').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Change Password Modal -->
<div id="modal-passwd" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Change Password</h3>
      <button onclick="document.getElementById('modal-passwd').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="passwd" />
      <input type="hidden" name="email" id="passwd-email" />
      <p class="text-sm text-gray-600 dark:text-gray-400">Account: <strong id="passwd-label" class="text-gray-900 dark:text-white"></strong></p>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
        <input type="password" name="password" placeholder="New password" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('modal-passwd').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Update</button>
      </div>
    </form>
  </div>
</div>

<!-- Delete Modal -->
<form method="POST" id="form-delete">
  <input type="hidden" name="action" value="delete" />
  <input type="hidden" name="email" id="delete-email" />
</form>
<div id="modal-delete" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl p-6">
    <div class="flex items-center gap-3 mb-4">
      <div class="flex h-10 w-10 items-center justify-center rounded-full bg-red-100"><i data-lucide="trash-2" class="h-5 w-5 text-red-600"></i></div>
      <div><h3 class="text-base font-semibold text-gray-900 dark:text-white">Delete Email Account</h3><p class="text-sm text-gray-500">This cannot be undone.</p></div>
    </div>
    <p class="text-sm text-gray-700 dark:text-gray-300 mb-5">Delete <strong id="delete-target" class="text-red-600"></strong>?</p>
    <div class="flex justify-end gap-2">
      <button onclick="document.getElementById('modal-delete').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
      <button onclick="document.getElementById('form-delete').submit()" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Delete</button>
    </div>
  </div>
</div>

<script>
function confirmDelete(email) {
  document.getElementById('delete-target').textContent = email;
  document.getElementById('delete-email').value = email;
  document.getElementById('modal-delete').classList.remove('hidden');
}
function openPasswd(email) {
  document.getElementById('passwd-email').value = email;
  document.getElementById('passwd-label').textContent = email;
  document.getElementById('modal-passwd').classList.remove('hidden');
}
</script>

<?php include '../includes/layout_end.php'; ?>
