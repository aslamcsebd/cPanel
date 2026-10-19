<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = ($_GET['msg'] ?? '') === 'created' ? 'Email account created.' : '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $r = $client->call('Email', 'add_pop', [
            'email'    => trim($_POST['email'] ?? ''),
            'domain'   => trim($_POST['domain'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'quota'    => (int)($_POST['quota'] ?? 0),
        ]);
        $cpanelErrors = $r['errors'] ?? [];
        if ($r['success'] && empty($cpanelErrors)) {
            $fullEmail = trim($_POST['email'] ?? '') . '@' . trim($_POST['domain'] ?? '');
            $config = readConfig();
            $config['email_passwords'][$fullEmail] = $_POST['password'];
            writeConfig($config);
            header('Location: accounts.php?msg=created'); exit;
        } else {
            $err = !empty($cpanelErrors) ? implode(' ', $cpanelErrors) : 'Failed to create account.';
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

$totalAccounts = count($accounts);

$totalUsedBytes = array_sum(array_column($accounts, 'diskused'));
$totalUsedMB    = round($totalUsedBytes / (1024 * 1024), 1);
$totalQuotaMB   = array_sum(array_column($accounts, 'quota'));
$unlimitedCount = count(array_filter($accounts, fn($a) => ($a['quota'] ?? 0) == 0));
$usagePct       = $totalQuotaMB > 0 ? min(100, round($totalUsedMB / $totalQuotaMB * 100)) : 0;
$usageBarColor  = $usagePct > 80 ? 'bg-red-500' : ($usagePct > 50 ? 'bg-yellow-500' : 'bg-blue-500');
$largestBytes   = max(array_column($accounts, 'diskused') ?: [0]);
$largestAcc     = $largestBytes > 0 ? array_values(array_filter($accounts, fn($a) => ($a['diskused'] ?? 0) === $largestBytes))[0] ?? null : null;
$largestEmail   = $largestAcc ? ($largestAcc['email'] ?? ($largestAcc['login'] . '@' . $largestAcc['domain'])) : null;
$largestMB      = round($largestBytes / (1024 * 1024), 1);

// Group by base username
$_baseCounts = [];
foreach ($accounts as $a) {
    $email = $a['email'] ?? ($a['login'] . '@' . $a['domain']);
    $user  = strstr($email, '@', true);
    $base  = strtolower(preg_replace('/[._\-].*$|\d+.*$/', '', $user) ?: $user);
    $_baseCounts[$base] = ($_baseCounts[$base] ?? 0) + 1;
}
$groupedAccounts = [];
foreach ($accounts as $a) {
    $email = $a['email'] ?? ($a['login'] . '@' . $a['domain']);
    $user  = strstr($email, '@', true);
    $base  = strtolower(preg_replace('/[._\-].*$|\d+.*$/', '', $user) ?: $user);
    $grp   = $_baseCounts[$base] >= 3 ? ucfirst($base) : 'Others';
    $groupedAccounts[$grp][] = $a;
}
ksort($groupedAccounts);
if (isset($groupedAccounts['Others'])) {
    $tmp = $groupedAccounts['Others']; unset($groupedAccounts['Others']); $groupedAccounts['Others'] = $tmp;
}

$pageTitle  = 'Email Accounts — cPanel Manager';
$activePage = 'email-accounts';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <div class="flex items-center gap-2">
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Email Accounts</h1>
    </div>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage email accounts across your domains</p>
  </div>
  <button onclick="openCreateModal()" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Create Account
  </button>
</div>

<?php if ($msg): ?><div id="flash-msg" class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><script>setTimeout(function(){var e=document.getElementById('flash-msg');if(e)e.remove();history.replaceState(null,'',location.pathname);},3000);</script><?php endif; ?>
<?php if ($err): ?><div id="flash-err" class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><script>setTimeout(function(){var e=document.getElementById('flash-err');if(e)e.remove();},3000);</script><?php endif; ?>

<!-- Stats -->
<?php if ($totalUsedMB > 0 || $largestEmail): ?>
<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-<?= ($largestEmail ? 2 : 1) ?>">
  <?php if ($totalUsedMB > 0): ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm h-full">
    <div class="flex items-center justify-between h-full">
      <div class="flex-1 min-w-0 mr-3">
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Usage</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= $totalUsedMB ?> <span class="text-sm font-normal text-gray-400">MB</span></p>
        <?php if ($totalQuotaMB > 0): ?>
        <div class="mt-2 flex items-center gap-2">
          <div class="flex-1 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
            <div class="h-full rounded-full <?= $usageBarColor ?>" style="width:<?= $usagePct ?>%"></div>
          </div>
          <span class="text-xs text-gray-400"><?= $usagePct ?>%</span>
        </div>
        <?php endif; ?>
      </div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/30 flex-shrink-0">
        <i data-lucide="hard-drive" class="h-6 w-6 text-green-600 dark:text-green-400"></i>
      </div>
    </div>
  </div>
  <?php endif; ?>
  <?php if ($largestEmail): ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm h-full">
    <div class="flex items-center justify-between h-full">
      <div class="flex-1 min-w-0 mr-3">
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Largest Mailbox</p>
        <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= $largestMB ?> <span class="text-sm font-normal text-gray-400">MB</span></p>
        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500 truncate" title="<?= htmlspecialchars($largestEmail) ?>"><?= htmlspecialchars($largestEmail) ?></p>
      </div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/30 flex-shrink-0">
        <i data-lucide="inbox" class="h-6 w-6 text-violet-600 dark:text-violet-400"></i>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- Group Tabs -->
<div class="flex flex-wrap items-center justify-between gap-2">
  <div class="flex flex-wrap gap-2">
  <?php $firstGrp = array_key_first($groupedAccounts); ?>
  <?php foreach ($groupedAccounts as $grp => $grpAccounts): ?>
  <button onclick="switchGroup('<?= htmlspecialchars($grp) ?>')" id="tab-<?= htmlspecialchars($grp) ?>"
    class="group-tab inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium border transition
    <?= $grp === $firstGrp ? 'bg-blue-600 text-white border-blue-600' : 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:border-blue-400' ?>">
    <?= htmlspecialchars($grp) ?>
    <span class="rounded-full px-1.5 py-0.5 text-xs <?= $grp === $firstGrp ? 'bg-blue-500 text-white' : 'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400' ?>"><?= count($grpAccounts) ?></span>
  </button>
  <?php endforeach; ?>
  </div>
  <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300">Total Accounts <span class="rounded-full px-1.5 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400"><?= $totalAccounts ?></span></span>
</div>

<!-- Accounts Cards -->
<?php foreach ($groupedAccounts as $grp => $grpAccounts): ?>
<div id="group-<?= htmlspecialchars($grp) ?>" class="account-group <?= $grp === $firstGrp ? '' : 'hidden' ?> grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
  <?php foreach ($grpAccounts as $acc):
    $email = $acc['email'] ?? ($acc['login'] . '@' . $acc['domain']);
    $quota = ($acc['quota'] ?? 0) == 0 ? 'Unlimited' : $acc['quota'] . ' MB';
    $used  = round(($acc['diskused'] ?? 0) / (1024*1024), 1) . ' MB';
  ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 shadow-sm hover:border-blue-400 dark:hover:border-blue-500 transition relative group">
    <a href="mailbox.php?account=<?= urlencode($email) ?>" class="flex items-center justify-between gap-3">
      <div class="min-w-0">
        <p class="text-sm font-medium text-gray-900 dark:text-white truncate"><?= htmlspecialchars($email) ?></p>
        <p class="mt-0.5 text-sm font-medium text-gray-500 dark:text-gray-400"><?= $used ?></p>
      </div>
      <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/30 flex-shrink-0">
        <i data-lucide="mail" class="h-5 w-5 text-blue-600 dark:text-blue-400"></i>
      </div>
    </a>
    <div class="absolute top-2 right-2 hidden group-hover:flex items-center gap-1 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-1">
      <a href="webmail_sso.php?email=<?= urlencode($email) ?>" target="_blank" class="inline-flex h-7 w-7 items-center justify-center rounded text-blue-500 hover:bg-blue-50" title="Open Webmail"><i data-lucide="external-link" class="h-3.5 w-3.5"></i></a>
      <button onclick="openPasswd('<?= htmlspecialchars($email) ?>')" class="inline-flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100" title="Change Password"><i data-lucide="key" class="h-3.5 w-3.5"></i></button>
      <button onclick="confirmDelete('<?= htmlspecialchars($email) ?>')" class="inline-flex h-7 w-7 items-center justify-center rounded text-red-400 hover:bg-red-50" title="Delete"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endforeach; ?>

<!-- Create Modal -->
<div id="modal-create-email" class="hidden fixed inset-0 z-50 flex items-center justify-center">
  <div class="fixed inset-0 bg-black/70" onclick="closeCreateModal()"></div>
  <div class="relative z-50 w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl mx-4">
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-t-xl">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Create Email Account</h3>
      <button onclick="closeCreateModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" id="form-create-email" class="px-5 pb-3 space-y-3" onsubmit="return validateCreateForm()" autocomplete="off">
      <input type="hidden" name="action" value="create" />
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Email Address</label>
        <div class="flex rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:border-blue-400 focus-within:ring-1 focus-within:ring-blue-400">
          <input type="text" id="create-username" name="email" placeholder="username" autocomplete="off" class="h-10 flex-1 bg-transparent px-3 text-sm outline-none min-w-0" required oninput="validateUsername(this.value)" />
          <select name="domain" id="create-domain-select" class="h-10 border-l border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-2 text-sm outline-none max-w-[160px]">
            <?php foreach ($domains as $d): ?><option value="<?= htmlspecialchars($d) ?>">@<?= htmlspecialchars($d) ?></option><?php endforeach; ?>
          </select>
          <button type="button" onclick="copyEmail(this)" class="px-2 border-l border-gray-200 dark:border-gray-600 text-xs text-gray-400 hover:text-blue-500 rounded-r-lg transition-colors" title="Copy email">Copy</button>
        </div>
        <p id="username-error" class="mt-1 text-xs text-red-500 hidden"></p>
      </div>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
        <div class="flex rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:border-blue-400 focus-within:ring-1 focus-within:ring-blue-400">
          <input type="text" id="create-password" name="password" placeholder="Enter password" autocomplete="off" class="h-10 flex-1 bg-transparent px-3 text-sm outline-none min-w-0" required />
          <button type="button" onclick="copyPassword('create-password', this)" class="px-2 border-l border-gray-200 dark:border-gray-600 text-xs text-gray-400 hover:text-blue-500 rounded-r-lg transition-colors" title="Copy password">Copy</button>
        </div>
        <div class="mt-1">
          <div class="flex items-center gap-2">
            <div class="flex-1 h-1 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
              <div id="password-strength-bar" class="h-full w-0 transition-all duration-300"></div>
            </div>
            <span id="password-strength-text" class="text-xs text-gray-400 w-12 text-right"></span>
          </div>
          <button type="button" onclick="generatePassword()" class="mt-1 text-xs text-blue-600 dark:text-blue-400 hover:underline">Generate strong password</button>
        </div>
        <!-- Password rules checklist -->
        <div class="mt-2 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 px-3 py-2">
          <div class="grid grid-cols-2 gap-x-2 gap-y-1">
          <div id="rule-length"  class="flex items-center gap-1.5 text-xs text-gray-400"><span class="rule-dot"><svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg></span> At least 8 characters</div>
          <div id="rule-upper"   class="flex items-center gap-1.5 text-xs text-gray-400"><span class="rule-dot"><svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg></span> One uppercase (A–Z)</div>
          <div id="rule-lower"   class="flex items-center gap-1.5 text-xs text-gray-400"><span class="rule-dot"><svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg></span> One lowercase (a–z)</div>
          <div id="rule-number"  class="flex items-center gap-1.5 text-xs text-gray-400"><span class="rule-dot"><svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg></span> One number (0–9)</div>
          <div id="rule-special" class="flex items-center gap-1.5 text-xs text-gray-400"><span class="rule-dot"><svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg></span> One special (!@#$%^&*)</div>
          </div>
        </div>
        <p id="password-error" class="mt-1 text-xs text-red-500 hidden"></p>
      </div>
      <input type="hidden" name="quota" value="50" />
      <div class="flex justify-between pt-3 border-t border-gray-100 dark:border-gray-700 -mx-5 px-5">
        <button type="button" onclick="closeCreateModal()" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create Account</button>
      </div>
    </form>
  </div>
</div>

<!-- Change Password Modal -->
<div id="modal-passwd" class="hidden fixed inset-0 z-50 flex items-center justify-center">
  <div class="fixed inset-0 bg-black/70" onclick="closePasswdModal()"></div>
  <div class="relative z-50 w-full max-w-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl mx-4">
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 rounded-t-xl">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Change Password</h3>
      <button onclick="closePasswdModal()" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" id="form-passwd" class="px-5 pt-2 pb-5 space-y-3" onsubmit="return validatePasswdForm()" autocomplete="off">
      <input type="hidden" name="action" value="passwd" />
      <input type="hidden" name="email" id="passwd-email" />
      <p class="text-sm text-gray-600 dark:text-gray-400">Account: <strong id="passwd-label" class="text-gray-900 dark:text-white"></strong></p>
      <div>
        <label class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
        <div class="flex rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 focus-within:border-blue-400 focus-within:ring-1 focus-within:ring-blue-400">
          <input type="text" id="passwd-password" name="password" placeholder="Enter new password" autocomplete="off" class="h-10 flex-1 bg-transparent px-3 text-sm outline-none min-w-0" required />
          <button type="button" onclick="copyPassword('passwd-password', this)" class="px-2 border-l border-gray-200 dark:border-gray-600 text-xs text-gray-400 hover:text-blue-500 rounded-r-lg transition-colors" title="Copy password">Copy</button>
        </div>
        <div class="mt-1">
          <div class="flex items-center gap-2">
            <div class="flex-1 h-0.5 rounded-full bg-gray-200 dark:bg-gray-700 overflow-hidden">
              <div id="passwd-strength-bar" class="h-full w-0 transition-all duration-300"></div>
            </div>
            <span id="passwd-strength-text" class="text-xs text-gray-400 w-12 text-right"></span>
          </div>
          <button type="button" onclick="generatePasswd()" class="mt-1 text-xs text-blue-600 dark:text-blue-400 hover:underline">Generate strong password</button>
        </div>
        <!-- Password rules checklist -->
        <div class="mt-2 rounded-lg bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 px-3 py-2">
          <div class="grid grid-cols-2 gap-x-2 gap-y-1">
          <div id="prule-length"  class="flex items-center gap-1.5 text-xs text-gray-400"><span class="rule-dot"><svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg></span> At least 8 characters</div>
          <div id="prule-upper"   class="flex items-center gap-1.5 text-xs text-gray-400"><span class="rule-dot"><svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg></span> One uppercase (A–Z)</div>
          <div id="prule-lower"   class="flex items-center gap-1.5 text-xs text-gray-400"><span class="rule-dot"><svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg></span> One lowercase (a–z)</div>
          <div id="prule-number"  class="flex items-center gap-1.5 text-xs text-gray-400"><span class="rule-dot"><svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg></span> One number (0–9)</div>
          <div id="prule-special" class="flex items-center gap-1.5 text-xs text-gray-400"><span class="rule-dot"><svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg></span> One special (!@#$%^&*)</div>
          </div>
        </div>
        <p id="passwd-error" class="mt-1 text-xs text-red-500 hidden"></p>
      </div>
      <div class="flex justify-between pt-3 border-t border-gray-100 dark:border-gray-700 -mx-5 px-5">
        <button type="button" onclick="closePasswdModal()" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
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
<div id="modal-delete" class="hidden fixed inset-0 z-50 flex items-center justify-center">
  <div class="fixed inset-0 bg-black/70" onclick="document.getElementById('modal-delete').classList.add('hidden')"></div>
  <div class="relative z-50 w-full max-w-sm rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl p-6 mx-4">
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
function copyEmail(btn) {
  const user = document.getElementById('create-username').value;
  const domain = document.getElementById('create-domain-select').value;
  if (!user) return;
  navigator.clipboard.writeText(user + '@' + domain).then(() => {
    btn.style.backgroundColor = '#22c55e';
    btn.style.color = '#fff';
    setTimeout(() => { btn.style.backgroundColor = ''; btn.style.color = ''; }, 1500);
  });
}
 function copyPassword(id, btn) {
  const val = document.getElementById(id).value;
  if (!val) return;
  navigator.clipboard.writeText(val).then(() => {
    btn.style.backgroundColor = '#22c55e';
    btn.style.color = '#fff';
    setTimeout(() => { btn.style.backgroundColor = ''; btn.style.color = ''; }, 1500);
  });
}
function validateUsername(val) {
  const err = document.getElementById('username-error');
  if (val.length > 0 && val.length < 3) {
    err.textContent = 'Username must be at least 3 characters.';
    err.classList.remove('hidden');
  } else if (val.length >= 3 && !/^[a-zA-Z0-9._-]+$/.test(val)) {
    err.textContent = 'Only letters, numbers, dots, hyphens, underscores allowed.';
    err.classList.remove('hidden');
  } else {
    err.classList.add('hidden');
  }
}
function checkRule(id, passed) {
  const el = document.getElementById(id);
  if (!el) return;
  const dot = el.querySelector('.rule-dot');
  if (passed) {
    el.className = 'flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400';
    dot.innerHTML = '<svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" fill="currentColor" opacity="0.2"/><path d="M3.5 6l1.8 1.8 3.2-3.6" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>';
  } else {
    el.className = 'flex items-center gap-1.5 text-xs text-gray-400';
    dot.innerHTML = '<svg class="h-3 w-3" viewBox="0 0 12 12" fill="none"><circle cx="6" cy="6" r="5" stroke="currentColor" stroke-width="1.5"/></svg>';
  }
}
function checkPasswordRules(password, prefix) {
  checkRule(prefix+'-length',  password.length >= 8);
  checkRule(prefix+'-upper',   /[A-Z]/.test(password));
  checkRule(prefix+'-lower',   /[a-z]/.test(password));
  checkRule(prefix+'-number',  /[0-9]/.test(password));
  checkRule(prefix+'-special', /[^a-zA-Z0-9]/.test(password));
}
function isPasswordValid(password) {
  return password.length >= 8;
}
function openCreateModal() {
  const now = new Date();
  const y  = now.getFullYear();
  const mo = String(now.getMonth()+1).padStart(2,'0');
  const d  = String(now.getDate()).padStart(2,'0');
  const h  = String(now.getHours()).padStart(2,'0');
  const mi = String(now.getMinutes()).padStart(2,'0');
  const username = y+mo+d+h+mi;
  document.getElementById('create-username').value = username;
  // Auto password: Aa@ + last 5 digits of username
  const pass = 'Aa@' + username;
  document.getElementById('create-password').value = pass;
  checkPasswordStrength(pass);
  document.getElementById('modal-create-email').classList.remove('hidden');
}
function switchGroup(grp) {
    document.querySelectorAll('.account-group').forEach(el => el.classList.add('hidden'));
    document.querySelectorAll('.group-tab').forEach(btn => {
        btn.classList.remove('bg-blue-600','text-white','border-blue-600');
        btn.classList.add('bg-white','dark:bg-gray-800','text-gray-600','dark:text-gray-300','border-gray-200','dark:border-gray-700');
        btn.querySelector('span').className = 'rounded-full px-1.5 py-0.5 text-xs bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400';
    });
    document.getElementById('group-' + grp).classList.remove('hidden');
    const tab = document.getElementById('tab-' + grp);
    tab.classList.add('bg-blue-600','text-white','border-blue-600');
    tab.classList.remove('bg-white','dark:bg-gray-800','text-gray-600','dark:text-gray-300','border-gray-200','dark:border-gray-700');
    tab.querySelector('span').className = 'rounded-full px-1.5 py-0.5 text-xs bg-blue-500 text-white';
}
function confirmDelete(email) {
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
  checkPasswordRules(password, 'rule');
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
  if (!isPasswordValid(password)) {
    passwordError.textContent = 'Password does not meet all requirements above.';
    passwordError.classList.remove('hidden');
    valid = false;
  }
  return valid;
}
function closePasswdModal() {
  document.getElementById('modal-passwd').classList.add('hidden');
  document.getElementById('form-passwd').reset();
  document.getElementById('passwd-strength-bar').style.width = '0%';
  document.getElementById('passwd-strength-text').textContent = '';
  document.getElementById('passwd-error').classList.add('hidden');
}
function generatePasswd() {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
  let pass = '';
  for (let i = 0; i < 16; i++) pass += chars.charAt(Math.floor(Math.random() * chars.length));
  document.getElementById('passwd-password').value = pass;
  checkPasswdStrength(pass);
}
function checkPasswdStrength(password) {
  let score = 0;
  if (password.length >= 8) score++;
  if (password.length >= 12) score++;
  if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
  if (/\d/.test(password)) score++;
  if (/[^a-zA-Z0-9]/.test(password)) score++;
  const bar = document.getElementById('passwd-strength-bar');
  const text = document.getElementById('passwd-strength-text');
  const colors = ['bg-red-500','bg-orange-500','bg-yellow-500','bg-blue-500','bg-green-500'];
  const labels = ['Very Weak','Weak','Fair','Good','Strong'];
  const pct = Math.min(100, (score / 5) * 100);
  bar.style.width = pct + '%';
  bar.className = 'h-full transition-all duration-300 ' + colors[Math.min(score, 4)];
  text.textContent = labels[Math.min(score, 4)];
  text.className = 'text-xs w-12 text-right ' + (score < 2 ? 'text-red-500' : score < 4 ? 'text-yellow-600' : 'text-green-600');
  checkPasswordRules(password, 'prule');
}
function validatePasswdForm() {
  const password = document.getElementById('passwd-password').value;
  const passwordError = document.getElementById('passwd-error');
  passwordError.classList.add('hidden');
  if (!isPasswordValid(password)) {
    passwordError.textContent = 'Password does not meet all requirements above.';
    passwordError.classList.remove('hidden');
    return false;
  }
  return true;
}
document.getElementById('create-password').addEventListener('input', function() {
  checkPasswordStrength(this.value);
});
document.getElementById('passwd-password').addEventListener('input', function() {
  checkPasswdStrength(this.value);
});
</script>

<?php include '../includes/layout_end.php'; ?>
