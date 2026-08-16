<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();

$logFile = __DIR__ . '/../logs/api.log';
$logs    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear') {
    file_put_contents($logFile, '');
}

if (file_exists($logFile)) {
    $lines = array_filter(explode("\n", file_get_contents($logFile)));
    foreach (array_reverse($lines) as $line) {
        $parts = explode(' | ', $line);
        if (count($parts) >= 6) {
            $logs[] = [
                'time'    => $parts[0],
                'module'  => $parts[1],
                'fn'      => $parts[2],
                'status'  => $parts[3],
                'dur'     => $parts[4],
                'result'  => $parts[5],
            ];
        }
    }
}

$pageTitle  = 'API Logs — cPanel Manager';
$activePage = 'api-logs';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">API Logs</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Live from logs/api.log — <?= count($logs) ?> entries</p>
  </div>
  <form method="POST" class="flex gap-2">
    <input type="hidden" name="action" value="clear" />
    <button type="submit" class="inline-flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-100">
      <i data-lucide="trash-2" class="h-4 w-4"></i> Clear Logs
    </button>
  </form>
</div>

<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Time</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Module</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Function</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">HTTP</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Duration</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Status</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php if (empty($logs)): ?>
        <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-400">No log entries yet. Visit the dashboard to generate API calls.</td></tr>
        <?php endif; ?>
        <?php foreach ($logs as $log):
          $ok = trim($log['result']) === 'OK';
          $statusBadge = $ok
            ? 'bg-green-50 text-green-700 dark:bg-green-900/20 dark:text-green-400'
            : 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400';
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3 text-xs font-mono text-gray-500 dark:text-gray-400 whitespace-nowrap"><?= htmlspecialchars($log['time']) ?></td>
          <td class="px-5 py-3 text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($log['module']) ?></td>
          <td class="px-5 py-3 text-sm font-mono text-gray-700 dark:text-gray-300"><?= htmlspecialchars($log['fn']) ?></td>
          <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($log['status']) ?></td>
          <td class="px-5 py-3 text-sm text-gray-500 dark:text-gray-400"><?= htmlspecialchars($log['dur']) ?></td>
          <td class="px-5 py-3"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $statusBadge ?>"><?= $ok ? 'OK' : 'FAIL' ?></span></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include '../includes/layout_end.php'; ?>
