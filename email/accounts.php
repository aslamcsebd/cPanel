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

$perPage = 12;
$totalAccounts = count($accounts);
$totalPages = max(1, (int)ceil($totalAccounts / $perPage));
$page = max(1, min($totalPages, (int)($_GET['page'] ?? 1)));
$offset = ($page - 1) * $perPage;
$pagedAccounts = array_slice($accounts, $offset, $perPage);

$pageTitle  = 'Email Accounts — cPanel Manager';
$activePage = 'email-accounts';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Email Accounts</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage email accounts across your domains</p>
  </div>
  <button onclick="document.getElementById('modal-create-email').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Create Account
  </button>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Stats -->
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm h-full">
    <div class="flex items-center justify-between h-full">
      <div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Accounts</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= $totalAccounts ?></p>
      </div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30">
        <i data-lucide="mail" class="h-6 w-6 text-blue-600 dark:text-blue-400"></i>
      </div>
    </div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm h-full">
    <div class="flex items-center justify-between h-full">
      <div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Usage</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= round(array_sum(array_column($accounts, 'diskused')) / (1024*1024), 1) ?> <span class="text-sm font-normal text-gray-400">MB</span></p>
      </div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/30">
        <i data-lucide="hard-drive" class="h-6 w-6 text-green-600 dark:text-green-400"></i>
      </div>
    </div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm h-full sm:col-span-2 xl:col-span-1">
    <div class="flex items-center justify-between h-full">
      <div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Domains</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= count($domains) ?></p>
      </div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-900/30">
        <i data-lucide="globe-2" class="h-6 w-6 text-indigo-600 dark:text-indigo-400"></i>
      </div>
    </div>
  </div>
</div>

<!-- Accounts Cards -->
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?php if (empty($pagedAccounts)): ?>
  <div class="sm:col-span-2 xl:col-span-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-8 text-center text-sm text-gray-400">
    <i data-lucide="inbox" class="h-8 w-8 mx-auto mb-2 text-gray-300"></i> No email accounts found.
  </div>
  <?php endif; ?>
  <?php foreach ($pagedAccounts as $acc):
    $email  = $acc['email'] ?? ($acc['login'] . '@' . $acc['domain']);
    $quota  = ($acc['quota'] ?? 0) == 0 ? 'Unlimited' : $acc['quota'] . ' MB';
    $used   = round(($acc['diskused'] ?? 0) / (1024*1024), 1) . ' MB';
  ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm hover:border-blue-400 dark:hover:border-blue-500 transition h-full relative group">
    <div class="flex items-center justify-between h-full">
      <div class="min-w-0">
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate"><?= htmlspecialchars($email) ?></p>
        <p class="mt-1 text-xl font-semibold text-gray-900 dark:text-white"><?= $used ?></p>
        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">Quota: <?= $quota ?></p>
      </div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30 flex-shrink-0">
        <i data-lucide="mail" class="h-6 w-6 text-blue-600 dark:text-blue-400"></i>
      </div>
    </div>
    <div class="absolute top-2 right-2 hidden group-hover:flex items-center gap-1 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-1">
      <a href="webmail_sso.php?email=<?= urlencode($email) ?>" target="_blank" class="inline-flex h-7 w-7 items-center justify-center rounded text-blue-500 hover:bg-blue-50" title="Open Webmail"><i data-lucide="external-link" class="h-3.5 w-3.5"></i></a>
      <button onclick="openPasswd('<?= htmlspecialchars($email) ?>')" class="inline-flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100" title="Change Password"><i data-lucide="key" class="h-3.5 w-3.5"></i></button>
      <button onclick="confirmDelete('<?= htmlspecialchars($email) ?>')" class="inline-flex h-7 w-7 items-center justify-center rounded text-red-400 hover:bg-red-50" title="Delete"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pagination -->
<?php if ($totalPages > 1): ?>
<div class="flex flex-col sm:flex-row items-center justify-between gap-4 pt-4">
  <span class="text-sm text-gray-500 dark:text-gray-400">Showing <?= $offset + 1 ?>–<?= min($offset + $perPage, $totalAccounts) ?> of <?= $totalAccounts ?> accounts</span>
  <div class="flex items-center gap-1">
    <?php if ($page > 1): ?>
      <a href="?page=1" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="First"><i data-lucide="chevrons-left" class="h-4 w-4 text-gray-500"></i></a>
      <a href="?page=<?= $page - 1 ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="Previous"><i data-lucide="chevron-left" class="h-4 w-4 text-gray-500"></i></a>
    <?php endif; ?>
    <?php
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);
    if ($start > 1) echo '<span class="h-8 w-8 flex items-center justify-center text-gray-400">…</span>';
    for ($i = $start; $i <= $end; $i++):
    ?>
      <?php if ($i === $page): ?>
        <span class="h-8 w-8 rounded bg-blue-600 text-white text-sm font-medium flex items-center justify-center"><?= $i ?></span>
      <?php else: ?>
        <a href="?page=<?= $i ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600"><?= $i ?></a>
      <?php endif; ?>
    <?php endfor; ?>
    <?php if ($end < $totalPages) echo '<span class="h-8 w-8 flex items-center justify-center text-gray-400">…</span>'; ?>
    <?php if ($page < $totalPages): ?>
      <a href="?page=<?= $page + 1 ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="Next"><i data-lucide="chevron-right" class="h-4 w-4 text-gray-500"></i></a>
      <a href="?page=<?= $totalPages ?>" class="h-8 w-8 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 flex items-center justify-center hover:bg-gray-50 dark:hover:bg-gray-600" title="Last"><i data-lucide="chevrons-right" class="h-4 w-4 text-gray-500"></i></a>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Create Modal -->
<div id="modal-create-email" class="hidden fixed inset-0 z-50 flex items-center justify-center">
  <div class="absolute inset-0 bg-black/60" onclick="closeCreateModal()"></div>
  <div class="relative w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl mx-4">
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-t-xl">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Create Email Account</h3>
      <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" id="form-create-email" class="p-5 space-y-3" onsubmit="return validateCreateForm()" autocomplete="off">
      <input type="hidden" name="action" value="create" />
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
        <div class="flex rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:border-blue-400 focus-within:ring-1 focus-within:ring-blue-400">
          <input type="text" id="create-username" name="email" placeholder="username" autocomplete="off" class="h-10 flex-1 bg-transparent px-3 text-sm outline-none min-w-0" required />
          <select name="domain" id="create-domain-select" class="h-10 border-l border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-2 text-sm outline-none rounded-r-lg max-w-[160px]">
            <?php foreach ($domains as $d): ?><option value="<?= htmlspecialchars($d) ?>">@<?= htmlspecialchars($d) ?></option><?php endforeach; ?>
          </select>
        </div>
        <p id="username-error" class="mt-1 text-xs text-red-500 hidden"></p>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
        <input type="password" id="create-password" name="password" placeholder="Enter password" autocomplete="new-password" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
        <div class="mt-1">
          <div class="flex items-center gap-2">
            <div class="flex-1 h-1.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
              <div id="password-strength-bar" class="h-full w-0 transition-all duration-300"></div>
            </div>
            <span id="password-strength-text" class="text-xs text-gray-400 w-12 text-right"></span>
          </div>
          <button type="button" onclick="generatePassword()" class="mt-1 text-xs text-blue-600 dark:text-blue-400 hover:underline">Generate strong password</button>
        </div>
        <p id="password-error" class="mt-1 text-xs text-red-500 hidden"></p>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Storage Quota</label>
        <div class="flex items-center gap-3">
          <input type="range" id="quota-slider" min="0" max="10240" step="100" value="1000" class="flex-1 h-2 rounded-lg appearance-none bg-gray-200 dark:bg-gray-700 cursor-pointer accent-blue-600" oninput="updateQuota(this.value)" />
          <div class="flex items-center gap-1">
            <input type="number" id="quota-input" name="quota" value="1000" min="0" step="100" class="h-9 w-20 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-2 text-sm text-center outline-none focus:border-blue-400" oninput="updateQuotaSlider(this.value)" />
            <span class="text-sm text-gray-500">MB</span>
          </div>
        </div>
      </div>
      <div class="flex justify-end gap-2 pt-1">
        <button type="button" onclick="closeCreateModal()" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create Account</button>
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
function closeCreateModal() {
  document.getElementById('modal-create-email').classList.add('hidden');
  document.getElementById('form-create-email').reset();
  document.getElementById('password-strength-bar').style.width = '0%';
  document.getElementById('password-strength-text').textContent = '';
  document.getElementById('username-error').classList.add('hidden');
  document.getElementById('password-error').classList.add('hidden');
}
function generatePassword() {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
  let pass = '';
  for (let i = 0; i < 16; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
  document.getElementById('create-password').value = pass;
  checkPasswordStrength(pass);
}
function checkPasswordStrength(password) {
  let score = 0;
  if (password.length >= 8) score++;
  if (password.length >= 12) score++;
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
  if (/\d/.test(password)) score++;
  if (/[^a-zA-Z0-9]/.test(password)) score++;
  const bar = document.getElementById('password-strength-bar');
  const text = document.getElementById('password-strength-text');
  const colors = ['bg-red-500','bg-orange-500','bg-yellow-500','bg-blue-500','bg-green-500'];
  const labels = ['Very Weak','Weak','Fair','Good','Strong'];
  const pct = Math.min(100, (score / 5) * 100);
  bar.style.width = pct + '%';
  bar.className = 'h-full transition-all duration-300 ' + colors[Math.min(score, 4)];
  text.textContent = labels[Math.min(score, 4)];
  text.className = 'text-xs w-12 text-right ' + (score < 2 ? 'text-red-500' : score < 4 ? 'text-yellow-600' : 'text-green-600');
}
function updateQuota(val) {
  document.getElementById('quota-input').value = val;
  document.getElementById('quota-slider').value = val;
}
function updateQuotaSlider(val) {
  document.getElementById('quota-slider').value = val;
  document.getElementById('quota-input').value = val;
}
function validateCreateForm() {
  const username = document.getElementById('create-username').value.trim();
  const password = document.getElementById('create-password').value;
  const usernameError = document.getElementById('username-error');
  const passwordError = document.getElementById('password-error');
  let valid = true;
  usernameError.classList.add('hidden');
  passwordError.classList.add('hidden');
  if (!/^[a-zA-Z0-9._-]+$/.test(username)) {
    usernameError.textContent = 'Username can only contain letters, numbers, dots, hyphens, and underscores.';
    usernameError.classList.remove('hidden');
    valid = false;
  }
  if (username.length < 3) {
    usernameError.textContent = 'Username must be at least 3 characters.';
    usernameError.classList.remove('hidden');
    valid = false;
  }
  if (password.length < 8) {
    passwordError.textContent = 'Password must be at least 8 characters.';
    passwordError.classList.remove('hidden');
    valid = false;
  }
  return valid;
}
document.getElementById('create-password').addEventListener('input', function() {
  checkPasswordStrength(this.value);
});
</script>

<?php include '../includes/layout_end.php'; ?>
