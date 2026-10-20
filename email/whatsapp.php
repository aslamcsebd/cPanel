<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/WhatsAppClient.php';
require_once '../api/CpanelClient.php';

$pageTitle  = 'WhatsApp Alerts — cPanel Manager';
$activePage = 'whatsapp-notify';
$basePath   = '../';

$config  = readConfig();
$success = '';
$error   = '';
$testResult = null;

// Fetch all email accounts for the monitor list
$allAccounts = [];
try {
    $cpanel      = new CpanelClient();
    $popsResult  = $cpanel->call('Email', 'list_pops');
    $allAccounts = array_map(
        fn($a) => $a['email'] ?? ($a['login'] . '@' . $a['domain']),
        $popsResult['data'] ?? []
    );
} catch (Throwable $e) {
    // Non-fatal — we'll show an empty list
}

// ── POST handlers ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_whatsapp') {
        $provider = $_POST['provider'] ?? 'callmebot';
        $old      = $config['whatsapp'] ?? [];

        $entry = [
            'enabled'             => !empty($_POST['enabled']),
            'provider'            => $provider,
            'phone'               => trim($_POST['phone'] ?? ''),
            'callmebot_apikey'    => trim($_POST['callmebot_apikey'] ?? '') ?: ($old['callmebot_apikey'] ?? ''),
            'ultramsg_instance'   => trim($_POST['ultramsg_instance'] ?? ''),
            'ultramsg_token'      => trim($_POST['ultramsg_token'] ?? '') ?: ($old['ultramsg_token'] ?? ''),
            'meta_phone_id'       => trim($_POST['meta_phone_id'] ?? ''),
            'meta_token'          => trim($_POST['meta_token'] ?? '') ?: ($old['meta_token'] ?? ''),
            'monitor_accounts'    => $_POST['monitor_accounts'] ?? [],
        ];

        $config['whatsapp'] = $entry;
        if (writeConfig($config)) {
            $success = 'WhatsApp alert settings saved.';
        } else {
            $error = 'Failed to save config. Check file permissions.';
        }
        $config = readConfig();

    } elseif ($action === 'test_whatsapp') {
        $wa = $config['whatsapp'] ?? [];
        if (empty($wa['phone'])) {
            $error = 'Save your settings first before testing.';
        } else {
            $client     = new WhatsAppClient($wa);
            $testResult = $client->test();
            if (!$testResult['success']) {
                $error = 'Test failed: ' . ($testResult['error'] ?? 'Unknown error');
            } else {
                $success = 'Test message sent! Check your WhatsApp.';
            }
        }

    } elseif ($action === 'clear_state') {
        unset($config['whatsapp_state']);
        writeConfig($config);
        $config  = readConfig();
        $success = 'Notification state cleared. All emails will appear as "new" on next check.';
    }
}

$wa      = $config['whatsapp'] ?? [];
$state   = $config['whatsapp_state'] ?? [];
$monitor = $wa['monitor_accounts'] ?? [];
$logFile = __DIR__ . '/../logs/whatsapp.log';

include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">WhatsApp Email Alerts</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get a WhatsApp message whenever a new email arrives in your monitored accounts.</p>
  </div>
  <div class="flex items-center gap-2">
    <?php if (!empty($wa['enabled'])): ?>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 dark:bg-green-900/20 px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-400">
      <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Alerts Active
    </span>
    <?php else: ?>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-500 dark:text-gray-400">
      <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span> Alerts Off
    </span>
    <?php endif; ?>
  </div>
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

<div class="space-y-6">

  <!-- How it works banner -->
  <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 flex items-start gap-3">
    <i data-lucide="info" class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"></i>
    <div class="text-sm text-blue-700 dark:text-blue-400 space-y-1">
      <p class="font-medium">How it works</p>
      <p>A cron job runs periodically, checks your IMAP accounts for new emails, and sends a WhatsApp message to your phone. Set up the cron command below after saving your settings.</p>
    </div>
  </div>

  <!-- Main Settings Form -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-5">Alert Settings</h2>
    <form method="POST" id="wa-form" class="space-y-5">
      <input type="hidden" name="action" value="save_whatsapp" />

      <!-- Enable toggle -->
      <div class="flex items-center justify-between rounded-lg border border-gray-200 dark:border-gray-600 px-4 py-3">
        <div>
          <p class="text-sm font-medium text-gray-900 dark:text-white">Enable WhatsApp Alerts</p>
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Turn notifications on or off without removing your settings.</p>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
          <input type="checkbox" name="enabled" value="1" <?= !empty($wa['enabled']) ? 'checked' : '' ?> class="sr-only peer" />
          <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-600 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
        </label>
      </div>

      <!-- Provider -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">API Provider</label>
        <select name="provider" id="provider-select" onchange="switchProvider(this.value)"
                class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
          <option value="callmebot"  <?= ($wa['provider'] ?? 'callmebot') === 'callmebot'  ? 'selected' : '' ?>>CallMeBot (Free — recommended)</option>
          <option value="ultramsg"   <?= ($wa['provider'] ?? '') === 'ultramsg'   ? 'selected' : '' ?>>UltraMsg (Paid/Trial)</option>
          <option value="meta"       <?= ($wa['provider'] ?? '') === 'meta'       ? 'selected' : '' ?>>Meta Cloud API (WhatsApp Business)</option>
        </select>
      </div>

      <!-- Phone number (all providers) -->
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Your WhatsApp Phone Number</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($wa['phone'] ?? '') ?>"
               placeholder="+8801XXXXXXXXX (include country code)"
               class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
        <p class="mt-1 text-xs text-gray-400">Include country code, e.g. +880 for Bangladesh.</p>
      </div>

      <!-- CallMeBot fields -->
      <div id="fields-callmebot" class="space-y-4 provider-fields">
        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-3 text-xs text-amber-700 dark:text-amber-400 space-y-1">
          <p class="font-medium">⚡ First-time CallMeBot setup (one-time only):</p>
          <p>1. Add <strong>+34 644 59 73 30</strong> to your WhatsApp contacts.</p>
          <p>2. Send this message to that number: <code class="bg-amber-100 dark:bg-amber-900/40 px-1 rounded">I allow callmebot to send me messages</code></p>
          <p>3. You'll receive an API key in reply — paste it below.</p>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">CallMeBot API Key</label>
          <div class="relative">
            <input type="text" name="callmebot_apikey" value="<?= htmlspecialchars($wa['callmebot_apikey'] ?? '') ?>"
                   placeholder="e.g. 1234567" data-secure
                   class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 pr-10 text-sm outline-none focus:border-blue-400" />
            <button type="button" onclick="togglePass(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
              <i data-lucide="eye" class="h-4 w-4"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- UltraMsg fields -->
      <div id="fields-ultramsg" class="space-y-4 provider-fields hidden">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">UltraMsg Instance ID</label>
          <input type="text" name="ultramsg_instance" value="<?= htmlspecialchars($wa['ultramsg_instance'] ?? '') ?>"
                 placeholder="e.g. instance12345"
                 class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">UltraMsg Token</label>
          <div class="relative">
            <input type="text" name="ultramsg_token" value="<?= htmlspecialchars($wa['ultramsg_token'] ?? '') ?>"
                   placeholder="Your UltraMsg token" data-secure
                   class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 pr-10 text-sm outline-none focus:border-blue-400" />
            <button type="button" onclick="togglePass(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
              <i data-lucide="eye" class="h-4 w-4"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Meta fields -->
      <div id="fields-meta" class="space-y-4 provider-fields hidden">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Phone Number ID</label>
          <input type="text" name="meta_phone_id" value="<?= htmlspecialchars($wa['meta_phone_id'] ?? '') ?>"
                 placeholder="From Meta Developer Console"
                 class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Access Token</label>
          <div class="relative">
            <input type="text" name="meta_token" value="<?= htmlspecialchars($wa['meta_token'] ?? '') ?>"
                   placeholder="Meta permanent access token" data-secure
                   class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 pr-10 text-sm outline-none focus:border-blue-400" />
            <button type="button" onclick="togglePass(this)" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
              <i data-lucide="eye" class="h-4 w-4"></i>
            </button>
          </div>
        </div>
      </div>

      <!-- Monitored accounts -->
      <div>
        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">
          Email Accounts to Monitor
          <span class="ml-1 text-xs font-normal text-gray-400">(leave all unchecked = monitor all)</span>
        </label>
        <?php if (empty($allAccounts)): ?>
        <p class="text-sm text-gray-400 italic">No email accounts found. Make sure cPanel is connected.</p>
        <?php else: ?>
        <div class="rounded-lg border border-gray-200 dark:border-gray-600 divide-y divide-gray-100 dark:divide-gray-700 max-h-48 overflow-y-auto">
          <?php foreach ($allAccounts as $acc): ?>
          <label class="flex items-center gap-3 px-4 py-2.5 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/40">
            <input type="checkbox" name="monitor_accounts[]" value="<?= htmlspecialchars($acc) ?>"
                   <?= in_array($acc, $monitor) ? 'checked' : '' ?>
                   class="h-4 w-4 rounded border-gray-300 text-blue-600" />
            <span class="text-sm text-gray-700 dark:text-gray-300"><?= htmlspecialchars($acc) ?></span>
            <?php
            $st = $state[$acc] ?? null;
            if ($st):
            ?>
            <span class="ml-auto text-xs text-gray-400">
              last checked <?= htmlspecialchars($st['last_checked'] ?? '') ?>
            </span>
            <?php endif; ?>
          </label>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Buttons -->
      <div class="flex items-center gap-3 pt-1 flex-wrap">
        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
          <i data-lucide="save" class="h-4 w-4"></i> Save Settings
        </button>
        <button type="submit" formaction="whatsapp.php" onclick="document.getElementById('wa-form').querySelector('[name=action]').value='test_whatsapp'"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">
          <i data-lucide="send" class="h-4 w-4"></i> Send Test Message
        </button>
      </div>
    </form>
  </div>

  <!-- Cron Setup -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-1">Cron Job Setup</h2>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Add one of these commands to cPanel → Cron Jobs to run the email checker automatically.</p>

    <div class="space-y-3">
      <div>
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Every minute (most responsive):</p>
        <div class="flex items-center gap-2">
          <code class="flex-1 block rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 px-4 py-2.5 text-sm font-mono text-gray-700 dark:text-gray-300 overflow-x-auto select-all">
            /usr/bin/php <?= htmlspecialchars(realpath(__DIR__ . '/../cron/email_check.php')) ?>
          </code>
          <button onclick="copyText(this, '/usr/bin/php <?= htmlspecialchars(realpath(__DIR__ . '/../cron/email_check.php')) ?>')"
                  class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 dark:border-gray-600 px-3 py-2 text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 flex-shrink-0">
            <i data-lucide="copy" class="h-3.5 w-3.5"></i> Copy
          </button>
        </div>
      </div>
      <div>
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1.5">Every 5 minutes (lower load):</p>
        <p class="text-xs text-gray-400">Set cron interval to "*/5 * * * *" with the same command above.</p>
      </div>
      <div class="rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-3 text-xs text-gray-500 dark:text-gray-400 space-y-1">
        <p class="font-medium text-gray-700 dark:text-gray-300">To add via cPanel Cron Jobs:</p>
        <p>Go to cPanel → Cron Jobs → Add New Cron Job → set minute to <code>*</code> (or <code>*/5</code>) → paste the command above.</p>
      </div>
    </div>
  </div>

  <!-- Notification State / Log -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
    <div class="flex items-center justify-between mb-4">
      <div>
        <h2 class="text-base font-semibold text-gray-900 dark:text-white">Monitor Status</h2>
        <p class="text-sm text-gray-500 dark:text-gray-400">Last known email counts per account.</p>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="clear_state" />
        <button type="submit" onclick="return confirm('Reset state? The next cron run will re-notify for all existing emails.')"
                class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 px-3 py-2 text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
          <i data-lucide="refresh-ccw" class="h-3.5 w-3.5"></i> Reset State
        </button>
      </form>
    </div>

    <?php if (empty($state)): ?>
    <p class="text-sm text-gray-400 italic">No state yet. Run the cron job once to see data here.</p>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-gray-100 dark:border-gray-700 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
            <th class="pb-2 text-left">Account</th>
            <th class="pb-2 text-right">Total</th>
            <th class="pb-2 text-right">Unseen</th>
            <th class="pb-2 text-right hidden sm:table-cell">Last Checked</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 dark:divide-gray-700">
          <?php foreach ($state as $acc => $st): ?>
          <tr>
            <td class="py-2 text-gray-700 dark:text-gray-300"><?= htmlspecialchars($acc) ?></td>
            <td class="py-2 text-right text-gray-600 dark:text-gray-400"><?= (int)($st['last_total'] ?? 0) ?></td>
            <td class="py-2 text-right <?= ($st['last_unseen'] ?? 0) > 0 ? 'text-orange-600 dark:text-orange-400 font-medium' : 'text-gray-600 dark:text-gray-400' ?>">
              <?= (int)($st['last_unseen'] ?? 0) ?>
            </td>
            <td class="py-2 text-right text-gray-400 text-xs hidden sm:table-cell"><?= htmlspecialchars($st['last_checked'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Recent Log -->
  <?php if (file_exists($logFile)): ?>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
    <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-4">Recent Log</h2>
    <?php
    $lines    = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $recent   = array_slice(array_reverse($lines), 0, 30);
    ?>
    <pre class="rounded-lg bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 p-4 text-xs text-gray-600 dark:text-gray-300 overflow-x-auto max-h-60 overflow-y-auto font-mono whitespace-pre-wrap"><?= htmlspecialchars(implode("\n", $recent)) ?></pre>
  </div>
  <?php endif; ?>

</div>

<script>
document.querySelectorAll('[data-secure]').forEach(f => f.type = 'password');

function togglePass(btn) {
  const f = btn.previousElementSibling;
  f.type = f.type === 'password' ? 'text' : 'password';
  btn.querySelector('i').setAttribute('data-lucide', f.type === 'password' ? 'eye' : 'eye-off');
  if (window.lucide) lucide.createIcons();
}

function switchProvider(val) {
  document.querySelectorAll('.provider-fields').forEach(el => el.classList.add('hidden'));
  const target = document.getElementById('fields-' + val);
  if (target) target.classList.remove('hidden');
}

function copyText(btn, text) {
  navigator.clipboard.writeText(text).then(() => {
    const icon = btn.querySelector('i');
    icon.setAttribute('data-lucide', 'check');
    if (window.lucide) lucide.createIcons();
    setTimeout(() => {
      icon.setAttribute('data-lucide', 'copy');
      if (window.lucide) lucide.createIcons();
    }, 1500);
  });
}

// Init provider display
switchProvider(document.getElementById('provider-select').value);
</script>

<?php include '../includes/layout_end.php'; ?>
