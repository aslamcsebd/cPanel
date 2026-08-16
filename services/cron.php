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
        $r = $client->call('Cron', 'add_cron', [
            'minute'  => trim($_POST['minute'] ?? '*'),
            'hour'    => trim($_POST['hour'] ?? '*'),
            'day'     => trim($_POST['day'] ?? '*'),
            'month'   => trim($_POST['month'] ?? '*'),
            'weekday' => trim($_POST['weekday'] ?? '*'),
            'command' => trim($_POST['command'] ?? ''),
        ]);
        $r['success'] ? $msg = 'Cron job added.' : $err = $r['errors'][0] ?? 'Failed.';
    } elseif ($action === 'delete') {
        $r = $client->call('Cron', 'remove_cron', [
            'minute'  => $_POST['minute'] ?? '',
            'hour'    => $_POST['hour'] ?? '',
            'day'     => $_POST['day'] ?? '',
            'month'   => $_POST['month'] ?? '',
            'weekday' => $_POST['weekday'] ?? '',
            'command' => $_POST['command'] ?? '',
        ]);
        $r['success'] ? $msg = 'Cron job removed.' : $err = $r['errors'][0] ?? 'Failed.';
    }
}

$result = $client->call('Cron', 'list_cron');
$crons  = $result['data'] ?? [];

$pageTitle  = 'Cron Jobs — cPanel Manager';
$activePage = 'cron';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Cron Jobs</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Cron/list_cron</p>
  </div>
  <button onclick="document.getElementById('modal-add').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="plus" class="h-4 w-4"></i> Add Cron Job
  </button>
</div>

<?php if ($msg): ?><div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2"><i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?></div><?php endif; ?>
<?php if ($err): ?><div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2"><i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?></div><?php endif; ?>

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Schedule</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Command</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($crons)): ?>
        <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-400">No cron jobs found.</td></tr>
        <?php endif; ?>
        <?php foreach ($crons as $c):
          $min  = $c['minute'] ?? '*';
          $hr   = $c['hour'] ?? '*';
          $day  = $c['day'] ?? '*';
          $mon  = $c['month'] ?? '*';
          $wday = $c['weekday'] ?? '*';
          $cmd  = $c['command'] ?? '';
          $schedule = "$min $hr $day $mon $wday";
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5">
            <span class="inline-flex items-center rounded-lg bg-orange-50 dark:bg-orange-900/20 px-2.5 py-1 text-xs font-mono font-medium text-orange-700 dark:text-orange-400"><?= htmlspecialchars($schedule) ?></span>
          </td>
          <td class="px-5 py-3.5 text-sm font-mono text-gray-700 dark:text-gray-300 max-w-xs truncate"><?= htmlspecialchars($cmd) ?></td>
          <td class="px-5 py-3.5 text-right">
            <form method="POST" class="inline">
              <input type="hidden" name="action" value="delete" />
              <input type="hidden" name="minute" value="<?= htmlspecialchars($min) ?>" />
              <input type="hidden" name="hour" value="<?= htmlspecialchars($hr) ?>" />
              <input type="hidden" name="day" value="<?= htmlspecialchars($day) ?>" />
              <input type="hidden" name="month" value="<?= htmlspecialchars($mon) ?>" />
              <input type="hidden" name="weekday" value="<?= htmlspecialchars($wday) ?>" />
              <input type="hidden" name="command" value="<?= htmlspecialchars($cmd) ?>" />
              <button type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Modal -->
<div id="modal-add" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add Cron Job</h3>
      <button onclick="document.getElementById('modal-add').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-6 space-y-4">
      <input type="hidden" name="action" value="create" />
      <div>
        <label class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Quick Presets</label>
        <div class="flex flex-wrap gap-2">
          <?php foreach (['Every minute'=>'* * * * *','Every hour'=>'0 * * * *','Daily'=>'0 0 * * *','Weekly'=>'0 0 * * 0','Monthly'=>'0 0 1 * *'] as $label => $val): ?>
          <button type="button" onclick="setSchedule('<?= $val ?>')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-blue-50 hover:border-blue-300 hover:text-blue-700"><?= $label ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="grid grid-cols-5 gap-2">
        <?php foreach ([['minute','Minute'],['hour','Hour'],['day','Day'],['month','Month'],['weekday','Weekday']] as [$n,$l]): ?>
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-500 dark:text-gray-400"><?= $l ?></label>
          <input type="text" name="<?= $n ?>" id="cron-<?= $n ?>" value="*" class="h-9 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-2 text-sm text-center outline-none focus:border-blue-400" />
        </div>
        <?php endforeach; ?>
      </div>
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Command</label>
        <input type="text" name="command" placeholder="/usr/bin/php /home/user/public_html/cron.php" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm font-mono outline-none focus:border-blue-400" required />
      </div>
      <div class="flex justify-end gap-2">
        <button type="button" onclick="document.getElementById('modal-add').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Add</button>
      </div>
    </form>
  </div>
</div>

<script>
function setSchedule(val) {
  const parts = val.split(' ');
  ['minute','hour','day','month','weekday'].forEach((f, i) => {
    document.getElementById('cron-' + f).value = parts[i] || '*';
  });
}
</script>

<?php include '../includes/layout_end.php'; ?>
