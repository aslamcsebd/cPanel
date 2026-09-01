<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

$client = new CpanelClient();
$msg = $err = '';

$pgCheck     = $client->call('Postgresql', 'list_databases');
$pgError     = $pgCheck['errors'][0] ?? '';
// Supported only if call succeeded AND no errors mentioning unsupported/disabled
$isSupported = $pgCheck['success'] && empty($pgCheck['errors']);

$databases = [];
$users     = [];

if ($isSupported) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'create_db') {
            $r = $client->call('Postgresql', 'create_database', ['name' => trim($_POST['name'] ?? '')]);
            $r['success'] ? $msg = 'Database created.' : $err = $r['errors'][0] ?? 'Failed.';
        } elseif ($action === 'delete_db') {
            $r = $client->call('Postgresql', 'delete_database', ['name' => $_POST['name'] ?? '']);
            $r['success'] ? $msg = 'Database deleted.' : $err = $r['errors'][0] ?? 'Failed.';
        } elseif ($action === 'create_user') {
            $r = $client->call('Postgresql', 'create_user', ['name' => trim($_POST['username'] ?? ''), 'password' => $_POST['password'] ?? '']);
            $r['success'] ? $msg = 'User created.' : $err = $r['errors'][0] ?? 'Failed.';
        } elseif ($action === 'delete_user') {
            $r = $client->call('Postgresql', 'delete_user', ['name' => $_POST['username'] ?? '']);
            $r['success'] ? $msg = 'User deleted.' : $err = $r['errors'][0] ?? 'Failed.';
        } elseif ($action === 'assign_user') {
            $r = $client->call('Postgresql', 'grant_access_to_database', ['user' => $_POST['username'] ?? '', 'database' => $_POST['database'] ?? '']);
            $r['success'] ? $msg = 'User assigned.' : $err = $r['errors'][0] ?? 'Failed.';
        } elseif ($action === 'revoke_user') {
            $r = $client->call('Postgresql', 'revoke_access_to_database', ['user' => $_POST['username'] ?? '', 'database' => $_POST['database'] ?? '']);
            $r['success'] ? $msg = 'Access revoked.' : $err = $r['errors'][0] ?? 'Failed.';
        }
    }

    $dbResult   = $client->call('Postgresql', 'list_databases');
    $databases  = $dbResult['data'] ?? [];
    $userResult = $client->call('Postgresql', 'list_users');
    $users      = array_map(fn($u) => $u['name'] ?? $u['username'] ?? $u, $userResult['data'] ?? []);
    $cfg    = getCpanelConfig(0);
    $prefix = ($cfg['username'] ?? '') . '_';
}

$search    = trim($_GET['q'] ?? '');
$activeTab = $_GET['tab'] ?? 'databases';

$filteredDbs = $search
    ? array_values(array_filter($databases, fn($db) => stripos($db['database'] ?? $db['name'] ?? '', $search) !== false))
    : array_values($databases);

$filteredUsers = $search
    ? array_values(array_filter($users, fn($u) => stripos($u, $search) !== false))
    : array_values($users);

$pageTitle  = 'PostgreSQL — cPanel Manager';
$activePage = 'databases-pg';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">PostgreSQL Databases</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Postgresql/list_databases · Postgresql/list_users</p>
  </div>
  <?php if ($isSupported): ?>
  <div class="flex gap-2">
    <button onclick="document.getElementById('modal-create-user').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">
      <i data-lucide="user-plus" class="h-4 w-4"></i> Add User
    </button>
    <button onclick="document.getElementById('modal-create-db').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
      <i data-lucide="plus" class="h-4 w-4"></i> Create Database
    </button>
  </div>
  <?php endif; ?>
</div>

<?php if (!$isSupported): ?>
<div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-8 text-center">
  <div class="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/30 mx-auto mb-4">
    <i data-lucide="database" class="h-8 w-8 text-amber-600 dark:text-amber-400"></i>
  </div>
  <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">PostgreSQL Not Available</h2>
  <p class="text-sm text-gray-600 dark:text-gray-400 max-w-md mx-auto mb-4"><?= $pgError ? htmlspecialchars($pgError) : 'PostgreSQL is not enabled on this cPanel account.' ?> Contact your hosting provider to enable it, or use MySQL instead.</p>
  <a href="mysql.php" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="database" class="h-4 w-4"></i> Go to MySQL
  </a>
</div>
<?php else: ?>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<!-- Stat Cards -->
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <div><p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Databases</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= count($databases) ?></p></div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/20"><i data-lucide="database" class="h-6 w-6 text-violet-600 dark:text-violet-400"></i></div>
    </div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <div><p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</p><p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white"><?= count($users) ?></p></div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20"><i data-lucide="users" class="h-6 w-6 text-blue-600 dark:text-blue-400"></i></div>
    </div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-5 shadow-sm">
    <div class="flex items-center justify-between">
      <div><p class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</p><p class="mt-1 text-2xl font-semibold text-green-600 dark:text-green-400">Active</p></div>
      <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-green-50 dark:bg-green-900/20"><i data-lucide="check-circle" class="h-6 w-6 text-green-600 dark:text-green-400"></i></div>
    </div>
  </div>
</div>

<!-- Search + Tabs -->
<div class="flex flex-col sm:flex-row sm:items-center gap-3">
  <form method="GET" class="flex gap-2 flex-1">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>" />
    <div class="relative flex-1 max-w-sm">
      <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 pointer-events-none"></i>
      <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search…" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 pl-9 pr-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100" />
    </div>
    <button type="submit" class="h-10 rounded-lg bg-blue-600 px-4 text-sm font-medium text-white hover:bg-blue-700">Search</button>
    <?php if ($search): ?><a href="?tab=<?= htmlspecialchars($activeTab) ?>" class="h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 flex items-center text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50">Clear</a><?php endif; ?>
  </form>
  <div class="flex border-b border-gray-200 dark:border-gray-700">
    <?php foreach (['databases' => 'Databases', 'users' => 'Users', 'assign' => 'Assign User'] as $tab => $label): ?>
    <a href="?tab=<?= $tab ?>&q=<?= urlencode($search) ?>" class="px-4 py-2.5 text-sm font-medium border-b-2 whitespace-nowrap <?= $activeTab === $tab ? 'text-blue-600 border-blue-600' : 'text-gray-500 dark:text-gray-400 border-transparent hover:text-gray-700 dark:hover:text-gray-200' ?>"><?= $label ?></a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Databases Tab -->
<?php if ($activeTab === 'databases'): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Databases</h2>
    <span class="text-xs text-gray-400"><?= count($filteredDbs) ?> total</span>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Database</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Assigned Users</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($filteredDbs)): ?>
        <tr><td colspan="3" class="px-5 py-10 text-center text-sm text-gray-400">No databases found.</td></tr>
        <?php endif; ?>
        <?php foreach ($filteredDbs as $db):
          $name    = $db['database'] ?? $db['name'] ?? '';
          $dbUsers = $db['users'] ?? [];
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5">
            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/20"><i data-lucide="database" class="h-4 w-4 text-violet-600 dark:text-violet-400"></i></div>
              <span class="text-sm font-medium font-mono text-gray-900 dark:text-gray-100"><?= htmlspecialchars($name) ?></span>
            </div>
          </td>
          <td class="px-5 py-3.5">
            <?php if (empty($dbUsers)): ?>
              <span class="text-xs text-gray-400">None</span>
            <?php else: ?>
              <div class="flex flex-wrap gap-1"><?php foreach ($dbUsers as $du): ?><span class="inline-flex items-center rounded-full bg-blue-50 dark:bg-blue-900/20 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-400"><?= htmlspecialchars($du) ?></span><?php endforeach; ?></div>
            <?php endif; ?>
          </td>
          <td class="px-5 py-3.5 text-right">
            <form method="POST" class="inline" onsubmit="return confirm('Delete <?= htmlspecialchars($name) ?>?')">
              <input type="hidden" name="action" value="delete_db" />
              <input type="hidden" name="name" value="<?= htmlspecialchars($name) ?>" />
              <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Users Tab -->
<?php if ($activeTab === 'users'): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">PostgreSQL Users</h2>
    <span class="text-xs text-gray-400"><?= count($filteredUsers) ?> total</span>
  </div>
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Username</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($filteredUsers)): ?>
        <tr><td colspan="2" class="px-5 py-10 text-center text-sm text-gray-400">No users found.</td></tr>
        <?php endif; ?>
        <?php foreach ($filteredUsers as $uname): ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5">
            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 dark:bg-blue-900/20"><i data-lucide="user" class="h-4 w-4 text-blue-600 dark:text-blue-400"></i></div>
              <span class="text-sm font-medium font-mono text-gray-900 dark:text-gray-100"><?= htmlspecialchars($uname) ?></span>
            </div>
          </td>
          <td class="px-5 py-3.5 text-right">
            <form method="POST" class="inline" onsubmit="return confirm('Delete user <?= htmlspecialchars($uname) ?>?')">
              <input type="hidden" name="action" value="delete_user" />
              <input type="hidden" name="username" value="<?= htmlspecialchars($uname) ?>" />
              <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<!-- Assign Tab -->
<?php if ($activeTab === 'assign'): ?>
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm">
  <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-700">
    <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Assign User to Database</h2>
  </div>
  <form method="POST" class="p-6 grid gap-4 sm:grid-cols-2">
    <input type="hidden" name="action" value="assign_user" />
    <div>
      <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">User</label>
      <select name="username" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100">
        <?php foreach ($users as $u): ?><option value="<?= htmlspecialchars($u) ?>"><?= htmlspecialchars($u) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Database</label>
      <select name="database" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400 dark:text-gray-100">
        <?php foreach ($databases as $db): $n = $db['database'] ?? $db['name'] ?? ''; ?><option value="<?= htmlspecialchars($n) ?>"><?= htmlspecialchars($n) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="sm:col-span-2 flex gap-3">
      <button type="submit" class="rounded-lg bg-blue-600 px-5 py-2 text-sm font-medium text-white hover:bg-blue-700">Assign Privileges</button>
    </div>
  </form>
  <div class="border-t border-gray-100 dark:border-gray-700 px-5 py-3">
    <p class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">Current Assignments</p>
    <?php
    $hasAny = false;
    foreach ($databases as $db):
      $dbName  = $db['database'] ?? $db['name'] ?? '';
      $dbUsers = $db['users'] ?? [];
      if (empty($dbUsers)) continue;
      $hasAny = true;
    ?>
    <div class="mb-3">
      <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1 font-mono"><?= htmlspecialchars($dbName) ?></p>
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
    <?php if (!$hasAny): ?><p class="text-sm text-gray-400">No assignments yet.</p><?php endif; ?>
  </div>
</div>
<?php endif; ?>

<!-- Create DB Modal -->
<div id="modal-create-db" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Create Database</h3>
      <button onclick="document.getElementById('modal-create-db').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="create_db" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Database Name</label>
        <div class="flex items-stretch rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 overflow-hidden focus-within:border-blue-400 w-full max-w-xs">
          <?php if (!empty($prefix)): ?>
          <span class="flex items-center px-3 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-600 border-r border-gray-200 dark:border-gray-600 select-none whitespace-nowrap"><?= htmlspecialchars($prefix) ?></span>
          <?php endif; ?>
          <input type="text" name="name" placeholder="mydb" required class="h-10 flex-1 px-3 text-sm outline-none bg-transparent dark:text-gray-100 min-w-0" />
        </div>
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('modal-create-db').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Create User Modal -->
<div id="modal-create-user" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-md rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Create PostgreSQL User</h3>
      <button onclick="document.getElementById('modal-create-user').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="create_user" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
        <div class="flex items-stretch rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 overflow-hidden focus-within:border-blue-400 w-full max-w-xs">
          <?php if (!empty($prefix)): ?>
          <span class="flex items-center px-3 text-sm text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-600 border-r border-gray-200 dark:border-gray-600 select-none whitespace-nowrap"><?= htmlspecialchars($prefix) ?></span>
          <?php endif; ?>
          <input type="text" name="username" placeholder="dbuser" required class="h-10 flex-1 px-3 text-sm outline-none bg-transparent dark:text-gray-100 min-w-0" />
        </div>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
        <input type="password" name="password" placeholder="Strong password" required class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('modal-create-user').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create</button>
      </div>
    </form>
  </div>
</div>

<?php endif; ?>

<?php include '../includes/layout_end.php'; ?>
