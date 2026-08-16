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
    } elseif ($action === 'create_user') {
        $r = $client->call('Mysql', 'create_user', [
            'name'     => trim($_POST['username'] ?? ''),
            'password' => $_POST['password'] ?? '',
        ]);
        $r['success'] ? $msg = 'User created.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete_user') {
        $r = $client->call('Mysql', 'delete_user', ['name' => $_POST['username'] ?? '']);
        $r['success'] ? $msg = 'User deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'set_privileges') {
        $r = $client->call('Mysql', 'set_privileges_on_database', [
            'user'       => $_POST['username'] ?? '',
            'database'   => $_POST['database'] ?? '',
            'privileges' => 'ALL PRIVILEGES',
        ]);
        $r['success'] ? $msg = 'Privileges set.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$dbResult   = $client->call('Mysql', 'list_databases');
$databases  = $dbResult['data'] ?? [];
$userResult = $client->call('Mysql', 'list_users');
$users      = $userResult['data'] ?? [];
$infoResult = $client->call('Mysql', 'get_server_information');
$serverInfo = $infoResult['data'] ?? [];

$pageTitle  = 'MySQL Databases — cPanel Manager';
$activePage = 'databases';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">MySQL Databases</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Mysql/list_databases, Mysql/list_users</p>
  </div>
  <button onclick="document.getElementById('modal-create-db').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Create Database
  </button>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
  <div class="flex flex-wrap gap-6">
    <div><p class="text-xs text-gray-500 dark:text-gray-400">MySQL Version</p><p class="text-sm font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($serverInfo['version'] ?? 'N/A') ?></p></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Total Databases</p><p class="text-sm font-semibold text-gray-900 dark:text-white"><?= count($databases) ?></p></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Total Users</p><p class="text-sm font-semibold text-gray-900 dark:text-white"><?= count($users) ?></p></div>
  </div>
</div>

<div class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
  <button onclick="switchTab('databases')" id="tab-databases" class="px-4 py-2.5 text-sm font-medium text-blue-600 border-b-2 border-blue-600">Databases</button>
  <button onclick="switchTab('users')" id="tab-users" class="px-4 py-2.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:text-gray-700 border-b-2 border-transparent">Users</button>
</div>

<!-- Databases Tab -->
<div id="panel-databases">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700/50">
          <tr>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Database</th>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Disk</th>
            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <?php if (empty($databases)): ?>
          <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400">No databases found.</td></tr>
          <?php endif; ?>
          <?php foreach ($databases as $db):
            $name = $db['database'] ?? $db;
            $disk = isset($db['disk_usage']) ? round($db['disk_usage'] / 1024 / 1024, 1) . ' MB' : '—';
          ?>
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-5 py-3.5">
              <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-violet-50 dark:bg-violet-900/20"><i data-lucide="database" class="h-4 w-4 text-violet-600 dark:text-violet-400"></i></div>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 font-mono"><?= htmlspecialchars($name) ?></span>
              </div>
            </td>
            <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300"><?= $disk ?></td>
            <td class="px-5 py-3.5 text-right">
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="delete_db" />
                <input type="hidden" name="name" value="<?= htmlspecialchars($name) ?>" />
                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50" title="Delete"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Users Tab -->
<div id="panel-users" class="hidden">
  <div class="flex justify-end mb-3">
    <button onclick="document.getElementById('modal-create-user').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
      <i data-lucide="plus" class="h-4 w-4"></i> Create User
    </button>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700/50">
          <tr>
            <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Username</th>
            <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <?php if (empty($users)): ?>
          <tr><td colspan="2" class="px-5 py-8 text-center text-sm text-gray-400">No users found.</td></tr>
          <?php endif; ?>
          <?php foreach ($users as $u):
            $uname = $u['user'] ?? $u;
          ?>
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-5 py-3.5 text-sm font-mono font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($uname) ?></td>
            <td class="px-5 py-3.5 text-right">
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="delete_user" />
                <input type="hidden" name="username" value="<?= htmlspecialchars($uname) ?>" />
                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

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
        <input type="text" name="name" placeholder="mydb" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
        <p class="mt-1 text-xs text-gray-400">cPanel will prefix with your username automatically.</p>
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
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Create MySQL User</h3>
      <button onclick="document.getElementById('modal-create-user').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="create_user" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
        <input type="text" name="username" placeholder="dbuser" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
        <input type="password" name="password" placeholder="Strong password" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('modal-create-user').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Create</button>
      </div>
    </form>
  </div>
</div>

<script>
function switchTab(tab) {
  ['databases','users'].forEach(t => {
    document.getElementById('panel-' + t).classList.toggle('hidden', t !== tab);
    const btn = document.getElementById('tab-' + t);
    if (t === tab) { btn.classList.add('text-blue-600','border-blue-600'); btn.classList.remove('text-gray-500','border-transparent'); }
    else { btn.classList.remove('text-blue-600','border-blue-600'); btn.classList.add('text-gray-500','border-transparent'); }
  });
}
</script>

<?php include '../includes/layout_end.php'; ?>
