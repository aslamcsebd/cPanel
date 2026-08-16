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
        $r = $client->call('Ftp', 'add_ftp', [
            'user'     => trim($_POST['user'] ?? ''),
            'pass'     => $_POST['pass'] ?? '',
            'quota'    => (int)($_POST['quota'] ?? 0),
            'homedir'  => trim($_POST['homedir'] ?? '/'),
        ]);
        $r['success'] ? $msg = 'FTP account created.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete') {
        $r = $client->call('Ftp', 'delete_ftp', [
            'user'        => $_POST['user'] ?? '',
            'destroy'     => 0,
            'disallow_del'=> 0,
        ]);
        $r['success'] ? $msg = 'FTP account deleted.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'passwd') {
        $r = $client->call('Ftp', 'passwd', [
            'user' => $_POST['user'] ?? '',
            'pass' => $_POST['pass'] ?? '',
        ]);
        $r['success'] ? $msg = 'Password updated.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$result = $client->call('Ftp', 'list_ftp_with_disk');
$ftps   = $result['data'] ?? [];

$pageTitle  = 'FTP Accounts — cPanel Manager';
$activePage = 'ftp';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">FTP Accounts</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Ftp/list_ftp_with_disk</p>
  </div>
  <button onclick="document.getElementById('modal-add').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Add FTP Account
  </button>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Username</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Home Directory</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Quota</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($ftps)): ?>
        <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-400">No FTP accounts found.</td></tr>
        <?php endif; ?>
        <?php foreach ($ftps as $f):
          $user  = $f['user'] ?? '';
          $dir   = $f['homedir'] ?? '';
          $quota = ($f['quota'] ?? 0) == 0 ? 'Unlimited' : $f['quota'] . ' MB';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5">
            <div class="flex items-center gap-3">
              <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-cyan-50 dark:bg-cyan-900/20"><i data-lucide="upload-cloud" class="h-4 w-4 text-cyan-600 dark:text-cyan-400"></i></div>
              <span class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($user) ?></span>
            </div>
          </td>
          <td class="px-5 py-3.5 text-sm font-mono text-gray-500 dark:text-gray-400"><?= htmlspecialchars($dir) ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-700 dark:text-gray-300"><?= $quota ?></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <button onclick="openPasswd('<?= htmlspecialchars($user) ?>')" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="Change Password"><i data-lucide="key" class="h-4 w-4"></i></button>
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="user" value="<?= htmlspecialchars($user) ?>" />
                <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
              </form>
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
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add FTP Account</h3>
      <button onclick="document.getElementById('modal-add').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="create" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
        <input type="text" name="user" placeholder="ftpuser" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
        <input type="password" name="pass" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Home Directory</label>
        <input type="text" name="homedir" placeholder="/public_html" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Quota MB (0 = unlimited)</label>
        <input type="number" name="quota" value="0" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('modal-add').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
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
      <input type="hidden" name="user" id="passwd-user" />
      <p class="text-sm text-gray-600 dark:text-gray-400">Account: <strong id="passwd-label" class="text-gray-900 dark:text-white"></strong></p>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
        <input type="password" name="pass" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required />
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('modal-passwd').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Update</button>
      </div>
    </form>
  </div>
</div>

<script>
function openPasswd(user) {
  document.getElementById('passwd-user').value = user;
  document.getElementById('passwd-label').textContent = user;
  document.getElementById('modal-passwd').classList.remove('hidden');
}
</script>

<?php include '../includes/layout_end.php'; ?>
