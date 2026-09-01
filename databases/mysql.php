<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_db') {
        $r = $client->call('Mysql', 'create_database', ['name' => trim($_POST['name'] ?? '')]);
        $r['success'] ? $msg = 'Database created.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete_db') {
        $r = $client->call('Mysql', 'delete_database', ['name' => $_POST['name'] ?? '']);
        $r['success'] ? $msg = 'Database deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'check_db') {
        $r = $client->call('Mysql', 'check_database', ['name' => $_POST['name'] ?? '']);
        $r['success'] ? $msg = 'Database check complete.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'repair_db') {
        $r = $client->call('Mysql', 'repair_database', ['name' => $_POST['name'] ?? '']);
        $r['success'] ? $msg = 'Database repaired.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'create_user') {
        $r = $client->call('Mysql', 'create_user', ['name' => trim($_POST['username'] ?? ''), 'password' => $_POST['password'] ?? '']);
        $r['success'] ? $msg = 'User created.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete_user') {
        $r = $client->call('Mysql', 'delete_user', ['name' => $_POST['username'] ?? '']);
        $r['success'] ? $msg = 'User deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'assign_user') {
        $r = $client->call('Mysql', 'set_privileges_on_database', ['user' => $_POST['username'] ?? '', 'database' => $_POST['database'] ?? '', 'privileges' => 'ALL PRIVILEGES']);
        $r['success'] ? $msg = 'User assigned to database.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'revoke_user') {
        $r = $client->call('Mysql', 'revoke_access_to_database', ['user' => $_POST['username'] ?? '', 'database' => $_POST['database'] ?? '']);
        $r['success'] ? $msg = 'Access revoked.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$dbResult   = $client->call('Mysql', 'list_databases');
$databases  = $dbResult['data'] ?? [];
$userResult = $client->call('Mysql', 'list_users');
$users      = array_map(fn($u) => $u['user'] ?? $u, $userResult['data'] ?? []);
$infoResult = $client->call('Mysql', 'get_server_information');
$serverInfo = $infoResult['data'] ?? [];
// Derive prefix from API first, fallback to config username + '_'
$cfg    = getCpanelConfig(0);
$prefix = ($serverInfo['prefix'] ?? '') ?: (($cfg['username'] ?? '') . '_');

$totalDisk = array_sum(array_column($databases, 'disk_usage'));

function fmtSize($bytes): string {
    $bytes = (int)$bytes;
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 0) . ' KB';
    return $bytes . ' bytes';
}

$search = trim($_GET['q'] ?? '');
$filteredDbs = $search
    ? array_values(array_filter($databases, fn($db) => stripos($db['database'] ?? '', $search) !== false))
    : array_values($databases);

$pageTitle  = 'MySQL Databases — cPanel Manager';
$activePage = 'databases';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">MySQL Databases</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage databases, users, and privileges</p>
  </div>
  <div class="flex gap-2">
    <a href="#section-create-db" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
      <i data-lucide="plus" class="h-4 w-4"></i> New Database
    </a>
    <a href="#section-users" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">
      <i data-lucide="user-plus" class="h-4 w-4"></i> Add User
    </a>
  </div>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- ── Stat Cards ── -->
<?php
  $assignedCount = 0;
  foreach ($databases as $db) { if (!empty($db['users'])) $assignedCount++; }
  $mysqlVersion = $serverInfo['version'] ?? 'N/A';
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
  <!-- Total Databases -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Databases</p>
        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white"><?= count($databases) ?></p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= fmtSize($totalDisk) ?> total</p>
      </div>
      <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100 dark:bg-violet-900/30">
        <i data-lucide="database" class="h-5 w-5 text-violet-600 dark:text-violet-400"></i>
      </div>
    </div>
    <div class="mt-3 flex items-center gap-1.5">
      <span class="inline-flex items-center gap-1 rounded-full bg-violet-50 dark:bg-violet-900/20 px-2 py-0.5 text-xs font-medium text-violet-700 dark:text-violet-400">
        <i data-lucide="check" class="h-3 w-3"></i> Active
      </span>
    </div>
  </div>

  <!-- Total Users -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">DB Users</p>
        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white"><?= count($users) ?></p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400"><?= $assignedCount ?> assigned</p>
      </div>
      <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 dark:bg-blue-900/30">
        <i data-lucide="users" class="h-5 w-5 text-blue-600 dark:text-blue-400"></i>
      </div>
    </div>
    <div class="mt-3 flex items-center gap-1.5">
      <?php if (count($users) > 0): ?>
      <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-400">
        <i data-lucide="check" class="h-3 w-3"></i> Configured
      </span>
      <?php else: ?>
      <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-900/20 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400">
        <i data-lucide="alert-triangle" class="h-3 w-3"></i> No users yet
      </span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Disk Usage -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Disk Usage</p>
        <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white"><?= fmtSize($totalDisk) ?></p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">across <?= count($databases) ?> databases</p>
      </div>
      <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 dark:bg-amber-900/30">
        <i data-lucide="hard-drive" class="h-5 w-5 text-amber-600 dark:text-amber-400"></i>
      </div>
    </div>
    <?php $pct = $totalDisk > 0 ? min(100, round($totalDisk / (500 * 1048576) * 100)) : 0; ?>
    <div class="mt-3">
      <div class="h-1.5 w-full rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
        <div class="h-full rounded-full <?= $pct > 80 ? 'bg-red-500' : ($pct > 50 ? 'bg-amber-500' : 'bg-green-500') ?>" style="width:<?= $pct ?>%"></div>
      </div>
    </div>
  </div>

  <!-- MySQL Version / Server Status -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">MySQL Server</p>
        <p class="mt-2 text-xl font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($mysqlVersion) ?></p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Server version</p>
      </div>
      <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 dark:bg-green-900/30">
        <i data-lucide="server" class="h-5 w-5 text-green-600 dark:text-green-400"></i>
      </div>
    </div>
    <div class="mt-3">
      <span class="inline-flex items-center gap-1 rounded-full bg-green-50 dark:bg-green-900/20 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">
        <span class="h-1.5 w-1.5 rounded-full bg-green-500 animate-pulse"></span> Online
      </span>
    </div>
  </div>
</div>

<!-- ── Create New Database ── -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm" id="section-create-db">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Create New Database</h2>
  </div>
  <form method="POST" class="p-5 flex flex-wrap items-end gap-3">
    <input type="hidden" name="action" value="create_db" />
    <div>
      <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">New Database</label>
      <div class="flex items-center rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 overflow-hidden focus-within:border-blue-400">
        <?php if ($prefix): ?>
        <span class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-600 border-r border-gray-200 dark:border-gray-600 select-none"><?= htmlspecialchars($prefix) ?></span>
        <?php endif; ?>
        <input type="text" name="name" placeholder="dbname" required class="h-10 px-3 text-sm outline-none bg-transparent dark:text-gray-100 w-48" />
      </div>
    </div>
    <button type="submit" class="h-10 rounded-lg bg-blue-600 px-5 text-sm font-medium text-white hover:bg-blue-700">Create Database</button>
  </form>
</div>

<!-- ── Modify Databases ── -->
<?php if (!empty($databases)): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Modify Databases</h2>
  </div>
  <div class="p-5 grid gap-4 sm:grid-cols-2">
    <form method="POST" class="flex flex-wrap items-end gap-3">
      <input type="hidden" name="action" value="check_db" />
      <div class="flex-1 min-w-0">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Check Database</label>
        <select name="name" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100">
          <?php foreach ($databases as $db): ?><option value="<?= htmlspecialchars($db['database'] ?? '') ?>"><?= htmlspecialchars($db['database'] ?? '') ?></option><?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="h-10 rounded-lg bg-gray-600 px-4 text-sm font-medium text-white hover:bg-gray-700">Check Database</button>
    </form>
    <form method="POST" class="flex flex-wrap items-end gap-3">
      <input type="hidden" name="action" value="repair_db" />
      <div class="flex-1 min-w-0">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Repair Database</label>
        <select name="name" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100">
          <?php foreach ($databases as $db): ?><option value="<?= htmlspecialchars($db['database'] ?? '') ?>"><?= htmlspecialchars($db['database'] ?? '') ?></option><?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="h-10 rounded-lg bg-amber-600 px-4 text-sm font-medium text-white hover:bg-amber-700">Repair Database</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ── Current Databases ── -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden" id="section-databases">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex flex-wrap items-center justify-between gap-3">
    <div class="flex items-center gap-3">
      <h2 class="text-base font-semibold text-gray-900 dark:text-white">Current Databases</h2>
      <span class="rounded-full bg-violet-50 dark:bg-violet-900/20 px-2.5 py-0.5 text-xs font-medium text-violet-700 dark:text-violet-400"><?= count($filteredDbs) ?></span>
    </div>
    <form method="GET" class="flex gap-2">
      <div class="relative">
        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none"></i>
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search databases…" class="h-8 w-48 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 pl-9 pr-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100" />
      </div>
      <button type="submit" class="h-8 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white hover:bg-blue-700">Go</button>
      <?php if ($search): ?><a href="?" class="h-8 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 flex items-center text-xs text-gray-600 dark:text-gray-300 hover:bg-gray-50">Clear</a><?php endif; ?>
    </form>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Database</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Size</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Privileged Users</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($filteredDbs)): ?>
        <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-gray-400">No databases found.</td></tr>
        <?php endif; ?>
        <?php foreach ($filteredDbs as $db):
          $name    = $db['database'] ?? '';
          $disk    = (int)($db['disk_usage'] ?? 0);
          $dbUsers = $db['users'] ?? [];
          $pct     = ($totalDisk > 0) ? min(100, round($disk / $totalDisk * 100)) : 0;
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 group">
          <td class="px-5 py-3.5">
            <div class="flex items-center gap-3">
              <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/20 group-hover:bg-violet-100 dark:group-hover:bg-violet-900/40 transition-colors">
                <i data-lucide="database" class="h-4 w-4 text-violet-600 dark:text-violet-400"></i>
              </div>
              <div>
                <span class="text-sm font-semibold font-mono text-gray-900 dark:text-gray-100"><?= htmlspecialchars($name) ?></span>
                <?php if (empty($dbUsers)): ?>
                <p class="text-xs text-gray-400 mt-0.5">No users assigned</p>
                <?php else: ?>
                <p class="text-xs text-blue-500 mt-0.5"><?= count($dbUsers) ?> user<?= count($dbUsers) > 1 ? 's' : '' ?> assigned</p>
                <?php endif; ?>
              </div>
            </div>
          </td>
          <td class="px-5 py-3.5">
            <div class="flex flex-col gap-1">
              <span class="text-sm font-medium text-gray-700 dark:text-gray-300"><?= fmtSize($disk) ?></span>
              <?php if ($totalDisk > 0): ?>
              <div class="w-20 h-1.5 rounded-full bg-gray-100 dark:bg-gray-700 overflow-hidden">
                <div class="h-full rounded-full <?= $pct > 50 ? 'bg-amber-500' : 'bg-violet-500' ?>" style="width:<?= $pct ?>%"></div>
              </div>
              <?php endif; ?>
            </div>
          </td>
          <td class="px-5 py-3.5">
            <?php if (empty($dbUsers)): ?>
              <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-500 dark:text-gray-400">
                <i data-lucide="minus" class="h-3 w-3"></i> None
              </span>
            <?php else: ?>
              <div class="flex flex-wrap gap-1.5">
                <?php foreach ($dbUsers as $du): ?>
                <form method="POST" class="inline">
                  <input type="hidden" name="action" value="revoke_user" />
                  <input type="hidden" name="username" value="<?= htmlspecialchars($du) ?>" />
                  <input type="hidden" name="database" value="<?= htmlspecialchars($name) ?>" />
                  <button type="submit" title="Revoke access" class="inline-flex items-center gap-1 rounded-full bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-400 hover:bg-red-50 hover:border-red-200 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400 transition-colors">
                    <i data-lucide="user-check" class="h-3 w-3"></i>
                    <?= htmlspecialchars($du) ?>
                    <i data-lucide="x" class="h-3 w-3 opacity-50"></i>
                  </button>
                </form>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </td>
          <td class="px-5 py-3.5 text-right">
            <form method="POST" class="inline" onsubmit="return confirm('Delete database <?= htmlspecialchars(addslashes($name)) ?>?')">
              <input type="hidden" name="action" value="delete_db" />
              <input type="hidden" name="name" value="<?= htmlspecialchars($name) ?>" />
              <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 dark:border-red-800 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete
              </button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- ── Database Users ── -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm" id="section-users">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Database Users</h2>
  </div>

  <!-- Add New User -->
  <div class="p-5 border-b border-gray-100 dark:border-gray-700">
    <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-4">Add New User</h3>
    <!-- prevent password autofill -->
    <input type="text" style="display:none" />
    <input type="password" autocomplete="off" style="display:none" />
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="create_user" />

      <!-- Username -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
        <div class="flex items-center gap-3">
          <div class="flex items-stretch w-full max-w-xs rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 overflow-hidden focus-within:border-blue-400">
            <?php if ($prefix): ?>
            <span class="flex items-center px-3 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-600 border-r border-gray-200 dark:border-gray-600 select-none whitespace-nowrap"><?= htmlspecialchars($prefix) ?></span>
            <?php endif; ?>
            <input type="text" name="username" id="new-username" autocomplete="off" required class="h-10 flex-1 px-3 text-sm outline-none bg-transparent dark:text-gray-100 min-w-0" />
          </div>
          <span id="user-ok" class="hidden text-green-500"><i data-lucide="check-circle" class="h-5 w-5"></i></span>
          <span id="user-err" class="hidden text-red-500 text-xs"></span>
        </div>
      </div>

      <!-- Password -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
        <div class="flex items-center gap-3">
          <div class="relative w-full max-w-xs">
            <input type="password" name="password" id="new-password" autocomplete="off" required
              class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 pr-10 text-sm outline-none focus:border-blue-400 dark:text-gray-100"
              oninput="updateStrength(this.value)" />
            <button type="button" onclick="togglePwd()" class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
              <i data-lucide="eye" class="h-4 w-4" id="pwd-eye"></i>
            </button>
          </div>
          <span id="pass-ok" class="hidden text-green-500"><i data-lucide="check-circle" class="h-5 w-5"></i></span>
        </div>
      </div>

      <!-- Password Again -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password (Again)</label>
        <div class="flex items-center gap-3">
          <input type="password" id="new-password2" autocomplete="off"
            class="h-10 w-full max-w-xs rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100"
            oninput="checkMatch()" />
          <span id="match-icon" class="hidden"></span>
        </div>
      </div>

      <!-- Strength -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Strength</label>
        <div class="flex items-center gap-3">
          <!-- Strength bar with overlaid text, like real cPanel -->
          <div class="relative w-full max-w-xs h-7 rounded border border-gray-200 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 overflow-hidden">
            <div id="strength-fill" class="absolute left-0 top-0 h-full transition-all duration-300 w-0" style="background-color:#e5e7eb"></div>
            <div class="absolute inset-0 flex items-center justify-center z-10">
              <span id="strength-label" class="text-xs font-medium text-gray-700 dark:text-gray-200">—</span>
            </div>
          </div>
          <button type="button" onclick="generatePassword()"
            class="h-8 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 whitespace-nowrap">
            Password Generator
          </button>
        </div>
      </div>

      <!-- Submit -->
      <div>
        <button type="submit" class="h-10 rounded-lg bg-blue-600 px-5 text-sm font-medium text-white hover:bg-blue-700">Create User</button>
      </div>
    </form>
  </div>

  <!-- Current Users -->
  <div>
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
      <div class="flex items-center gap-3">
        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Current Users</h3>
        <span class="rounded-full bg-blue-50 dark:bg-blue-900/20 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-400"><?= count($users) ?></span>
      </div>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700/50">
          <tr>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Users</th>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <?php if (empty($users)): ?>
          <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400">There are no users associated with your account.</td></tr>
          <?php endif; ?>
          <?php
          // Build a map of which databases each user is assigned to
          $userDbs = [];
          foreach ($databases as $db) {
            foreach ($db['users'] ?? [] as $du) {
              $userDbs[$du][] = $db['database'] ?? '';
            }
          }
          ?>
          <?php foreach ($users as $uname):
            $assignedTo = $userDbs[$uname] ?? [];
          ?>
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 group">
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20 group-hover:bg-blue-100 dark:group-hover:bg-blue-900/40 transition-colors">
                  <i data-lucide="user" class="h-4 w-4 text-blue-600 dark:text-blue-400"></i>
                </div>
                <div>
                  <span class="text-sm font-semibold font-mono text-gray-900 dark:text-gray-100"><?= htmlspecialchars($uname) ?></span>
                  <?php if (!empty($assignedTo)): ?>
                  <p class="text-xs text-blue-500 mt-0.5"><?= implode(', ', array_map('htmlspecialchars', $assignedTo)) ?></p>
                  <?php else: ?>
                  <p class="text-xs text-gray-400 mt-0.5">Not assigned to any database</p>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td class="px-5 py-3.5">
              <?php if (!empty($assignedTo)): ?>
              <span class="inline-flex items-center gap-1 rounded-full bg-green-50 dark:bg-green-900/20 px-2.5 py-1 text-xs font-medium text-green-700 dark:text-green-400">
                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Active
              </span>
              <?php else: ?>
              <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 dark:bg-amber-900/20 px-2.5 py-1 text-xs font-medium text-amber-700 dark:text-amber-400">
                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span> Unassigned
              </span>
              <?php endif; ?>
            </td>
            <td class="px-5 py-3.5 text-right">
              <form method="POST" class="inline" onsubmit="return confirm('Delete user <?= htmlspecialchars(addslashes($uname)) ?>?')">
                <input type="hidden" name="action" value="delete_user" />
                <input type="hidden" name="username" value="<?= htmlspecialchars($uname) ?>" />
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 dark:border-red-800 px-3 py-1.5 text-xs font-medium text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                  <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete
                </button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ── Add User To Database ── -->
<?php if (!empty($users) && !empty($databases)): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm" id="section-assign">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white">Add User To Database</h2>
  </div>
  <form method="POST" class="p-5 flex flex-wrap items-end gap-4">
    <input type="hidden" name="action" value="assign_user" />
    <div>
      <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">User</label>
      <select name="username" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100">
        <?php foreach ($users as $u): ?><option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Database</label>
      <select name="database" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100">
        <?php foreach ($databases as $db): ?><option value="<?= htmlspecialchars($db['database'] ?? '') ?>"><?= htmlspecialchars($db['database'] ?? '') ?></option><?php endforeach; ?>
      </select>
    </div>
    <button type="submit" class="h-10 rounded-lg bg-blue-600 px-5 text-sm font-medium text-white hover:bg-blue-700">Add</button>
  </form>

  <!-- Current Assignments -->
  <?php
  $hasAny = false;
  foreach ($databases as $db) { if (!empty($db['users'])) { $hasAny = true; break; } }
  if ($hasAny): ?>
  <div class="border-t border-gray-100 dark:border-gray-700 px-5 py-4">
    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">Current Assignments</p>
    <?php foreach ($databases as $db):
      $dbName  = $db['database'] ?? '';
      $dbUsers = $db['users'] ?? [];
      if (empty($dbUsers)) continue;
    ?>
    <div class="mb-3">
      <p class="text-xs font-medium font-mono text-gray-700 dark:text-gray-300 mb-1.5"><?= htmlspecialchars($dbName) ?></p>
      <div class="flex flex-wrap gap-2">
        <?php foreach ($dbUsers as $du): ?>
        <form method="POST" class="inline">
          <input type="hidden" name="action" value="revoke_user" />
          <input type="hidden" name="username" value="<?= htmlspecialchars($du) ?>" />
          <input type="hidden" name="database" value="<?= htmlspecialchars($dbName) ?>" />
          <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 dark:bg-blue-900/20 px-2.5 py-1 text-xs font-medium text-blue-700 dark:text-blue-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/20 dark:hover:text-red-400">
            <?= htmlspecialchars($du) ?> <i data-lucide="x" class="h-3 w-3"></i>
          </button>
        </form>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
function updateStrength(pwd) {
    let score = 0;
    if (pwd.length >= 8)   score += 20;
    if (pwd.length >= 12)  score += 10;
    if (/[A-Z]/.test(pwd)) score += 20;
    if (/[a-z]/.test(pwd)) score += 15;
    if (/[0-9]/.test(pwd)) score += 20;
    if (/[^A-Za-z0-9]/.test(pwd)) score += 15;
    score = Math.min(100, score);

    const fill  = document.getElementById('strength-fill');
    const label = document.getElementById('strength-label');
    fill.style.width = score + '%';
    let color, text;
    if (!pwd)            { color = '#e5e7eb'; text = '—'; }
    else if (score < 30) { color = '#ef4444'; text = 'Very Weak (' + score + '/100)'; }
    else if (score < 50) { color = '#f97316'; text = 'Weak (' + score + '/100)'; }
    else if (score < 70) { color = '#eab308'; text = 'Fair (' + score + '/100)'; }
    else if (score < 90) { color = '#84cc16'; text = 'Good (' + score + '/100)'; }
    else                 { color = '#C5FF00'; text = 'Strong (' + score + '/100)'; }
    fill.style.backgroundColor = color;
    label.textContent = text;

    const ok = document.getElementById('pass-ok');
    if (score >= 50) ok.classList.remove('hidden'); else ok.classList.add('hidden');
    checkMatch();
}
function checkMatch() {
    const p1 = document.getElementById('new-password').value;
    const p2 = document.getElementById('new-password2').value;
    const el = document.getElementById('match-icon');
    if (!p2) { el.classList.add('hidden'); el.innerHTML = ''; return; }
    el.classList.remove('hidden');
    if (p1 === p2) { el.innerHTML = '<span class="text-green-500"><svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg></span>'; }
    else           { el.innerHTML = '<span class="text-red-500 text-xs">Passwords do not match</span>'; }
}
function togglePwd() {
    const f = document.getElementById('new-password');
    const e = document.getElementById('pwd-eye');
    if (f.type === 'password') { f.type = 'text';     e.setAttribute('data-lucide', 'eye-off'); }
    else                       { f.type = 'password'; e.setAttribute('data-lucide', 'eye'); }
    lucide.createIcons();
}
function generatePassword() {
    const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    let pwd = '';
    const arr = new Uint8Array(16);
    crypto.getRandomValues(arr);
    arr.forEach(b => pwd += chars[b % chars.length]);
    const f = document.getElementById('new-password');
    const f2 = document.getElementById('new-password2');
    f.type = 'text'; f.value = pwd;
    f2.value = pwd;
    document.getElementById('pwd-eye').setAttribute('data-lucide', 'eye-off');
    lucide.createIcons();
    updateStrength(pwd);
    checkMatch();
}
document.getElementById('new-username').addEventListener('input', function() {
    const ok = document.getElementById('user-ok');
    this.value.trim().length > 0 ? ok.classList.remove('hidden') : ok.classList.add('hidden');
});
</script>

<?php include '../includes/layout_end.php'; ?>
