<?php
require_once 'includes/auth.php';
requireSetup();
requireLogin();

$basePath = '';
$pageTitle = 'Settings — cPanel Manager';
$activePage = 'settings';

$config  = readConfig();
$success = '';
$error   = '';
$testResult = null;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_cpanel') {
        $idx = (int)($_POST['cpanel_index'] ?? -1);
        $old = $config['cpanels'][$idx] ?? [];
        $entry = [
            'label'           => trim($_POST['label'] ?? ''),
            'host'            => preg_replace('#^https?://#', '', trim($_POST['host'] ?? '')),
            'port'            => (int)($_POST['port'] ?? 2083),
            'username'        => trim($_POST['cpanel_username'] ?? ''),
            'cpanel_password' => trim($_POST['cpanel_password'] ?? '') ?: ($old['cpanel_password'] ?? ''),
            'api_token'       => trim($_POST['api_token'] ?? '') ?: ($old['api_token'] ?? ''),
            'imap_host'       => preg_replace('#^https?://#', '', trim($_POST['imap_host'] ?? '')) ?: (preg_replace('#^https?://#', '', trim($_POST['host'] ?? '')) ?: ($old['imap_host'] ?? '')),
            'imap_port'       => (int)($_POST['imap_port'] ?? 993),
        ];
        if ($idx === -1) {
            $config['cpanels'][] = $entry;
        } else {
            $config['cpanels'][$idx] = $entry;
        }
        $config['cpanels'] = array_values($config['cpanels']);
        writeConfig($config);
        $success = 'cPanel server saved.';

    } elseif ($action === 'delete_cpanel') {
        $idx = (int)($_POST['cpanel_index']);
        array_splice($config['cpanels'], $idx, 1);
        if (($config['active_server'] ?? 0) >= count($config['cpanels'])) {
            $config['active_server'] = 0;
        }
        writeConfig($config);
        $success = 'cPanel server removed.';

    } elseif ($action === 'switch_server') {
        $config['active_server'] = (int)($_POST['active_server'] ?? 0);
        writeConfig($config);
        $success = 'Active server switched.';

    } elseif ($action === 'test_connection') {
        $idx = (int)($_POST['cpanel_index'] ?? 0);
        require_once 'api/CpanelClient.php';
        $client     = new CpanelClient($idx);
        $testResult = $client->testConnection();

    } elseif ($action === 'save_app') {
        $newUser = trim($_POST['app_username'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';
        $current = $_POST['current_password'] ?? '';

        if (!password_verify($current, $config['app']['password'])) {
            $error = 'Current password is incorrect.';
        } elseif ($newPass && $newPass !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif ($newPass && strlen($newPass) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            if ($newUser) $config['app']['username'] = $newUser;
            if ($newPass) $config['app']['password'] = password_hash($newPass, PASSWORD_DEFAULT);
            writeConfig($config);
            $_SESSION['app_user'] = $config['app']['username'];
            $success = 'Account settings saved.';
        }

    } elseif ($action === 'save_sidebar') {
        $sidebar = $config['sidebar'] ?? getSidebarItems();
        $enabledMap = [];
        foreach ($_POST['sidebar_enabled'] ?? [] as $id => $val) {
            $enabledMap[$id] = true;
        }
        foreach ($sidebar as &$item) {
            $item['enabled'] = !empty($enabledMap[$item['id']]);
        }
        $config['sidebar'] = $sidebar;
        writeConfig($config);
        $success = 'Sidebar settings saved.';
    }
    $config = readConfig();
}

$setupMode = !empty($_GET['setup']);

function serverForm(int $idx, array $cp = []): string {
    $isNew    = $idx === -1;
    $sameImap = empty($cp['imap_host']) || ($cp['imap_host'] === ($cp['host'] ?? ''));
    ob_start(); ?>
    <form method="POST" class="grid grid-cols-6 gap-4">
      <input type="hidden" name="action" value="save_cpanel" />
      <input type="hidden" name="cpanel_index" value="<?= $idx ?>" />

      <!-- Label: full width -->
      <div class="col-span-6">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Label</label>
        <input type="text" name="label" value="<?= htmlspecialchars($cp['label'] ?? '') ?>" placeholder="e.g. Main Server" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>

      <!-- Host: 5col | cPanel Port: 1col -->
      <div class="col-span-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Host</label>
        <input type="text" name="host" id="host-<?= $idx ?>" value="<?= htmlspecialchars($cp['host'] ?? '') ?>" placeholder="example.com" oninput="syncImapHost('<?= $idx ?>')" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Port</label>
        <input type="number" name="port" value="<?= (int)($cp['port'] ?? 2083) ?>" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>

      <!-- Username: full width -->
      <div class="col-span-6">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
        <input type="text" name="cpanel_username" value="<?= htmlspecialchars($cp['username'] ?? '') ?>" placeholder="cpanel username" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>

      <!-- cPanel Password: full width -->
      <div class="col-span-6">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">cPanel Password</label>
        <div class="relative">
          <input type="text" name="cpanel_password" value="<?= htmlspecialchars($cp['cpanel_password'] ?? '') ?>" placeholder="Enter password" data-secure class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 pr-10 text-sm outline-none focus:border-blue-400" />
          <button type="button" onclick="togglePass(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"><i data-lucide="eye" class="h-4 w-4"></i></button>
        </div>
      </div>

      <!-- API Token: full width -->
      <div class="col-span-6">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">API Token</label>
        <div class="relative">
          <input type="text" name="api_token" value="<?= htmlspecialchars($cp['api_token'] ?? '') ?>" placeholder="Paste API token" data-secure class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 pr-10 text-sm outline-none focus:border-blue-400" />
          <button type="button" onclick="togglePass(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"><i data-lucide="eye" class="h-4 w-4"></i></button>
        </div>
      </div>

      <!-- IMAP Host: 5col | IMAP Port: 1col -->
      <div class="col-span-5">
        <div class="flex items-center justify-between mb-1.5">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">IMAP Host</label>
          <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="checkbox" id="imap-same-<?= $idx ?>" onchange="toggleImapHost('<?= $idx ?>')" <?= $sameImap ? 'checked' : '' ?> class="rounded border-gray-300 text-blue-600" />
            <span class="text-xs text-gray-500 dark:text-gray-400">Same as Host</span>
          </label>
        </div>
        <input type="text" name="imap_host" id="imap-host-<?= $idx ?>" value="<?= htmlspecialchars($cp['imap_host'] ?? '') ?>" placeholder="mail.example.com" <?= $sameImap ? 'disabled' : '' ?> class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400 disabled:opacity-50 disabled:cursor-not-allowed" />
      </div>
      <div class="col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">IMAP Port</label>
        <input type="number" name="imap_port" value="<?= (int)($cp['imap_port'] ?? 993) ?>" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>

      <!-- Buttons -->
      <div class="col-span-6 flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
          <i data-lucide="<?= $isNew ? 'plus' : 'save' ?>" class="h-4 w-4"></i> <?= $isNew ? 'Add Server' : 'Save' ?>
        </button>
        <button type="submit" onclick="this.form.querySelector('[name=action]').value='test_connection'" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">
          <i data-lucide="plug" class="h-4 w-4"></i> Test Connection
        </button>
        <?php if ($isNew): ?>
        <button type="button" onclick="this.closest('.new-server-card').remove()" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50">
          <i data-lucide="x" class="h-4 w-4"></i> Cancel
        </button>
        <?php endif; ?>
      </div>
    </form>
    <?php return ob_get_clean();
}

include 'includes/layout.php';
?>

<?php if ($setupMode): ?>
<div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 flex items-start gap-3">
  <i data-lucide="info" class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"></i>
  <div>
    <p class="text-sm font-medium text-blue-800 dark:text-blue-300">Almost there!</p>
    <p class="text-sm text-blue-700 dark:text-blue-400 mt-0.5">Enter your cPanel credentials below to connect the dashboard to your hosting account.</p>
  </div>
</div>
<?php endif; ?>

<div>
  <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Settings</h1>
  <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage cPanel connection and application settings.</p>
</div>

<?php if ($success): ?>
<div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2">
  <i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2">
  <i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<?php if ($testResult !== null): ?>
<div class="rounded-lg border <?= $testResult['success'] ? 'border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-900/20' : 'border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20' ?> px-4 py-3 text-sm flex items-center gap-2 <?= $testResult['success'] ? 'text-green-700 dark:text-green-400' : 'text-red-700 dark:text-red-400' ?>">
  <i data-lucide="<?= $testResult['success'] ? 'check-circle' : 'x-circle' ?>" class="h-4 w-4"></i>
  <?php if ($testResult['success']): ?>
    Connected successfully! Primary domain: <strong><?= htmlspecialchars($testResult['data']['domain'] ?? 'N/A') ?></strong> — <?= $testResult['duration'] ?>
  <?php else: ?>
    Connection failed: <?= htmlspecialchars($testResult['errors'][0] ?? 'Unknown error') ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="space-y-6">

    <!-- cPanel Servers -->
    <div class="space-y-4">

      <?php $cpanels = $config['cpanels'] ?? []; $activeIdx = (int)($config['active_server'] ?? 0); ?>

      <?php if (count($cpanels) > 1): ?>
      <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm">
        <form method="POST" class="flex items-center gap-3">
          <input type="hidden" name="action" value="switch_server" />
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">Active Server:</label>
          <select name="active_server" class="flex-1 h-10 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
            <?php foreach ($cpanels as $i => $cp): ?>
            <option value="<?= $i ?>" <?= $i === $activeIdx ? 'selected' : '' ?>><?= htmlspecialchars($cp['label'] ?: $cp['host']) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Switch</button>
        </form>
      </div>
      <?php endif; ?>

      <?php foreach ($cpanels as $i => $cp): ?>
      <div class="rounded-xl border <?= $i === $activeIdx ? 'border-blue-400 dark:border-blue-500' : 'border-gray-200 dark:border-gray-700' ?> bg-white dark:bg-gray-800 p-6 shadow-sm">
        <div class="flex items-center justify-between mb-5">
          <div class="flex items-center gap-2">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($cp['label'] ?: $cp['host']) ?></h2>
            <?php if ($i === $activeIdx): ?><span class="text-xs bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-400 px-2 py-0.5 rounded-full">Active</span><?php endif; ?>
            <span class="text-xs text-gray-400">#<?= $i + 1 ?></span>
          </div>
          <form method="POST" onsubmit="return confirm('Remove this server?')">
            <input type="hidden" name="action" value="delete_cpanel" />
            <input type="hidden" name="cpanel_index" value="<?= $i ?>" />
            <button type="submit" class="text-red-400 hover:text-red-600"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
          </form>
        </div>
        <?= serverForm($i, $cp) ?>
      </div>
      <?php endforeach; ?>

      <!-- Add Server Button -->
      <div>
        <button onclick="appendNewServerForm()" class="inline-flex items-center gap-2 rounded-lg border border-dashed border-blue-400 dark:border-blue-500 px-4 py-2.5 text-sm font-medium text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-900/20">
          <i data-lucide="plus" class="h-4 w-4"></i> Add Server
        </button>
      </div>

      <div id="new-server-forms" class="space-y-4"></div>

    </div>

    <!-- Sidebar Configuration -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
      <div class="flex items-center justify-between mb-5">
        <div>
          <h2 class="text-base font-semibold text-gray-900 dark:text-white">Sidebar</h2>
          <p class="text-sm text-gray-500 dark:text-gray-400">Show or hide sidebar navigation items.</p>
        </div>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="save_sidebar" />
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <?php
          $sidebar = getSidebarItems();
          $sections = [];
          foreach ($sidebar as $item) {
            $sec = $item['section'] ?: 'GENERAL';
            $sections[$sec][] = $item;
          }
          foreach ($sections as $sec => $items):
          ?>
          <div id="sidebar-section-<?= htmlspecialchars($sec) ?>" class="rounded-lg border border-gray-200 dark:border-gray-700 p-4">
            <div class="flex items-center justify-between mb-3">
              <p class="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500"><?= htmlspecialchars($sec) ?></p>
              <label class="flex items-center gap-1.5 cursor-pointer">
                <input type="checkbox" class="sidebar-check-all h-3.5 w-3.5 rounded border-gray-300 text-blue-600" data-section="<?= htmlspecialchars($sec) ?>" onchange="toggleSection(this)" />
                <span class="text-xs text-gray-500 dark:text-gray-400">Check all</span>
              </label>
            </div>
            <div class="space-y-3">
              <?php foreach ($items as $item): ?>
              <label class="flex items-center justify-between cursor-pointer">
                <span class="text-sm text-gray-700 dark:text-gray-300 flex items-center gap-2">
                  <i data-lucide="<?= htmlspecialchars($item['icon']) ?>" class="h-4 w-4 text-gray-400"></i>
                  <?= htmlspecialchars($item['label']) ?>
                </span>
                <input type="checkbox" name="sidebar_enabled[<?= htmlspecialchars($item['id']) ?>]" value="1" <?= !empty($item['enabled']) ? 'checked' : '' ?> class="h-4 w-4 rounded border-gray-300 text-blue-600" />
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="mt-5">
          <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
            <i data-lucide="save" class="h-4 w-4"></i> Save Sidebar
          </button>
        </div>
      </form>
    </div>

    <!-- App Account -->
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
      <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-5">App Account</h2>
      <form method="POST" class="grid gap-5 md:grid-cols-2">
        <input type="hidden" name="action" value="save_app" />
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
          <input type="text" name="app_username" value="<?= htmlspecialchars($config['app']['username']) ?>" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Current Password</label>
          <input type="password" name="current_password" placeholder="Required to save changes" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">New Password</label>
          <input type="password" name="new_password" placeholder="Leave blank to keep current" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm New Password</label>
          <input type="password" name="confirm_password" placeholder="Repeat new password" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
        </div>
        <div class="md:col-span-2">
          <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">Save Account</button>
        </div>
      </form>
    </div>

</div>

<script>
// Hide all secure fields on load (show as password by default)
document.querySelectorAll('[data-secure]').forEach(f => f.type = 'password');

function togglePass(btn) {
  const f = btn.previousElementSibling;
  f.type = f.type === 'password' ? 'text' : 'password';
  btn.querySelector('i').setAttribute('data-lucide', f.type === 'password' ? 'eye' : 'eye-off');
  if (window.lucide) lucide.createIcons();
}
function toggleImapHost(idx) {
  const cb   = document.getElementById('imap-same-' + idx);
  const host = document.getElementById('imap-host-' + idx);
  const src  = document.getElementById('host-' + idx);
  if (cb.checked) {
    host.value    = src ? src.value : '';
    host.disabled = true;
  } else {
    host.disabled = false;
    host.focus();
  }
}
function syncImapHost(idx) {
  const cb = document.getElementById('imap-same-' + idx);
  if (cb && cb.checked) {
    document.getElementById('imap-host-' + idx).value = document.getElementById('host-' + idx).value;
  }
}
function appendNewServerForm() {
  const container = document.getElementById('new-server-forms');
  const div = document.createElement('div');
  const idx = 'new-' + Date.now();
  div.className = 'new-server-card rounded-xl border border-dashed border-blue-400 dark:border-blue-500 bg-white dark:bg-gray-800 p-6 shadow-sm';
  div.innerHTML = `
    <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-5">New Server</h2>
    <form method="POST" class="grid grid-cols-6 gap-4">
      <input type="hidden" name="action" value="save_cpanel" />
      <input type="hidden" name="cpanel_index" value="-1" />
      <div class="col-span-6">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Label</label>
        <input type="text" name="label" placeholder="e.g. Backup Server" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="col-span-5">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Host</label>
        <input type="text" name="host" id="host-${idx}" placeholder="example.com" oninput="syncImapHost('${idx}')" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Port</label>
        <input type="number" name="port" value="2083" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="col-span-6">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
        <input type="text" name="cpanel_username" placeholder="cpanel username" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="col-span-6">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">cPanel Password</label>
        <div class="relative">
          <input type="password" name="cpanel_password" placeholder="Enter password" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 pr-10 text-sm outline-none focus:border-blue-400" />
          <button type="button" onclick="togglePass(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"><i data-lucide="eye" class="h-4 w-4"></i></button>
        </div>
      </div>
      <div class="col-span-6">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">API Token</label>
        <div class="relative">
          <input type="password" name="api_token" placeholder="Paste API token" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 pr-10 text-sm outline-none focus:border-blue-400" />
          <button type="button" onclick="togglePass(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"><i data-lucide="eye" class="h-4 w-4"></i></button>
        </div>
      </div>
      <div class="col-span-5">
        <div class="flex items-center justify-between mb-1.5">
          <label class="text-sm font-medium text-gray-700 dark:text-gray-300">IMAP Host</label>
          <label class="flex items-center gap-1.5 cursor-pointer">
            <input type="checkbox" id="imap-same-${idx}" onchange="toggleImapHost('${idx}')" class="rounded border-gray-300 text-blue-600" />
            <span class="text-xs text-gray-500 dark:text-gray-400">Same as Host</span>
          </label>
        </div>
        <input type="text" name="imap_host" id="imap-host-${idx}" placeholder="mail.example.com" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="col-span-1">
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">IMAP Port</label>
        <input type="number" name="imap_port" value="993" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <div class="col-span-6 flex items-center gap-3">
        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
          <i data-lucide="plus" class="h-4 w-4"></i> Add Server
        </button>
        <button type="submit" onclick="this.form.querySelector('[name=action]').value='test_connection'" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">
          <i data-lucide="plug" class="h-4 w-4"></i> Test Connection
        </button>
        <button type="button" onclick="this.closest('.new-server-card').remove()" class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-500 dark:text-gray-400 hover:bg-gray-50">
          <i data-lucide="x" class="h-4 w-4"></i> Cancel
        </button>
      </div>
    </form>`;
  container.appendChild(div);
  if (window.lucide) lucide.createIcons();
  div.scrollIntoView({ behavior: 'smooth', block: 'start' });
}
function toggleSection(cb) {
  const section = cb.getAttribute('data-section');
  const container = document.getElementById('sidebar-section-' + section);
  if (!container) return;
  container.querySelectorAll('input[type=checkbox][name^="sidebar_enabled"]').forEach(function(c) {
    c.checked = cb.checked;
  });
}
function syncCheckAll() {
  document.querySelectorAll('.sidebar-check-all').forEach(function(cb) {
    const section = cb.getAttribute('data-section');
    const container = document.getElementById('sidebar-section-' + section);
    if (!container) return;
    const checkboxes = container.querySelectorAll('input[type=checkbox][name^="sidebar_enabled"]');
    cb.checked = checkboxes.length > 0 && Array.from(checkboxes).every(c => c.checked);
  });
}
syncCheckAll();
</script>

<?php include 'includes/layout_end.php'; ?>
