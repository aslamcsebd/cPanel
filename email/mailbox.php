<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';
require_once '../api/ImapClient.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$cpanel = new CpanelClient();
$err    = '';
$activeIdx = getActiveServerIndex();
$activeCp  = getCpanelConfig($activeIdx);

$popsResult  = $cpanel->call('Email', 'list_pops');
$allAccounts = array_map(fn($a) => $a['email'] ?? ($a['login'] . '@' . $a['domain']), $popsResult['data'] ?? []);

$currentEmail = $_GET['account'] ?? $_SESSION['imap_current'] ?? '';
// If no email selected, pick first account that has a saved password
if (!$currentEmail) {
    $config = readConfig();
    foreach ($allAccounts as $acc) {
        if (!empty($config['email_passwords'][$acc])) { $currentEmail = $acc; break; }
    }
    if (!$currentEmail) $currentEmail = $allAccounts[0] ?? '';
}
if ($currentEmail) $_SESSION['imap_current'] = $currentEmail;

// Group by base username (before . _ - or digits), 3+ = own group, else Others
$_baseCounts = [];
foreach ($allAccounts as $acc) {
    $user = strstr($acc, '@', true);
    $base = strtolower(preg_replace('/[._\-].*$|\d+.*$/', '', $user) ?: $user);
    $_baseCounts[$base] = ($_baseCounts[$base] ?? 0) + 1;
}
$groupedAccounts = ['Others' => []];
foreach ($allAccounts as $acc) {
    $user = strstr($acc, '@', true);
    $base = strtolower(preg_replace('/[._\-].*$|\d+.*$/', '', $user) ?: $user);
    if ($_baseCounts[$base] >= 3) {
        $groupedAccounts[ucfirst($base)][] = $acc;
    } else {
        $groupedAccounts['Others'][] = $acc;
    }
}
if (empty($groupedAccounts['Others'])) unset($groupedAccounts['Others']);
else { $tmp = $groupedAccounts['Others']; unset($groupedAccounts['Others']); ksort($groupedAccounts); $groupedAccounts['Others'] = $tmp; }

$groupedJson = json_encode($groupedAccounts, JSON_HEX_TAG);
$currentGroup = 'Others';
foreach ($groupedAccounts as $grp => $emails) {
    if (in_array($currentEmail, $emails)) { $currentGroup = $grp; break; }
}

$folder  = $_GET['folder'] ?? 'INBOX';
$page    = max(1, (int)($_GET['page'] ?? 1));
$viewSeq = isset($_GET['msg']) ? (int)$_GET['msg'] : null;
$search  = trim($_GET['search'] ?? '');
$msg     = '';

function getImapPass(string $email): ?string {
    if (!empty($_SESSION['imap_credentials'][$email])) return $_SESSION['imap_credentials'][$email];
    $config = readConfig();
    $pass   = $config['email_passwords'][$email] ?? null;
    if ($pass) { $_SESSION['imap_credentials'][$email] = $pass; return $pass; }
    return null;
}

function saveImapPass(string $email, string $pass): void {
    $config = readConfig();
    $config['email_passwords'][$email] = $pass;
    writeConfig($config);
    $_SESSION['imap_credentials'][$email] = $pass;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_pass') {
    $saveEmail = trim($_POST['email'] ?? '');
    $savePass  = $_POST['password'] ?? '';
    if ($saveEmail && $savePass) {
        $testImap = new ImapClient($saveEmail, $savePass);
        if ($testImap->testConnection()) {
            saveImapPass($saveEmail, $savePass);
            header('Location: mailbox.php?account=' . urlencode($saveEmail)); exit;
        } else {
            $err = 'Password incorrect or IMAP connection failed.';
        }
    }
}

// IMAP actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['imap_action'])) {
    $imapPass = getImapPass($currentEmail);
    if ($imapPass) {
        $imap      = new ImapClient($currentEmail, $imapPass);
        $actFolder = $_POST['folder'] ?? 'INBOX';
        $actSeq    = (int)($_POST['seq'] ?? 0);
        switch ($_POST['imap_action']) {
            case 'delete':
                $imap->deleteMessage($actFolder, $actSeq);
                header('Location: mailbox.php?account=' . urlencode($currentEmail) . '&folder=' . urlencode($actFolder)); exit;
            case 'move':
                $imap->moveMessage($actFolder, $actSeq, $_POST['dest'] ?? 'INBOX.Trash');
                header('Location: mailbox.php?account=' . urlencode($currentEmail) . '&folder=' . urlencode($actFolder)); exit;
            case 'flag':
                $imap->setFlag($actFolder, $actSeq, $_POST['flag'] ?? 'Seen', ($_POST['set'] ?? '1') === '1');
                header('Location: mailbox.php?account=' . urlencode($currentEmail) . '&folder=' . urlencode($actFolder) . '&msg=' . $actSeq); exit;
            case 'send':
                $cfg  = getImapConfig();
                $pass = getImapPass($currentEmail);
                $to      = trim($_POST['to'] ?? '');
                $subject = trim($_POST['subject'] ?? '');
                $body    = trim($_POST['body'] ?? '');
                $date    = date('r');
                $raw  = "From: {$currentEmail}\r\n";
                $raw .= "To: {$to}\r\n";
                $raw .= "Subject: {$subject}\r\n";
                $raw .= "Date: {$date}\r\n";
                $raw .= "MIME-Version: 1.0\r\n";
                $raw .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
                $raw .= $body;
                $r = $imap->sendMail($currentEmail, $to, $subject, $body, $cfg['smtp_host'], $cfg['smtp_port'], $pass);
                if ($r['success']) {
                    $imap->appendMessage('INBOX.Sent', $raw);
                    $msg = 'Email sent.';
                } else {
                    $err = 'Send failed: ' . ($r['error'] ?? 'Unknown error');
                }
                break;
        }
    }
}

if (isset($_GET['imap_logout'])) {
    unset($_SESSION['imap_credentials'][$currentEmail]);
    header('Location: mailbox.php'); exit;
}

$imapCfg      = getImapConfig();
$isConnected  = false;
$folders      = [];
$folderCounts = [];
$mailData     = ['messages' => [], 'total' => 0, 'unseen' => 0];
$openMessage  = null;

if ($currentEmail && $imapCfg['host']) {
    $imapPass = getImapPass($currentEmail);

    // Try cPanel master password first if no IMAP password saved
    if (!$imapPass && !empty($activeCp['cpanel_password'])) {
        $testImap = new ImapClient($currentEmail, $activeCp['cpanel_password']);
        if ($testImap->testConnection()) {
            saveImapPass($currentEmail, $activeCp['cpanel_password']);
            $imapPass = $activeCp['cpanel_password'];
        }
    }
    // If still no password, reset via cPanel API and save
    if (!$imapPass && !empty($activeCp['api_token'])) {
        $newPass = 'Cp@' . substr(md5($currentEmail . ($activeCp['cpanel_password'] ?? '')), 0, 12) . '!';
        $ch = curl_init("https://{$activeCp['host']}:{$activeCp['port']}/execute/Email/passwd_pop");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false, CURLOPT_POST => true,
            CURLOPT_POSTFIELDS     => http_build_query(['email' => $currentEmail, 'password' => $newPass]),
            CURLOPT_HTTPHEADER     => ["Authorization: cpanel {$activeCp['username']}:{$activeCp['api_token']}"],
            CURLOPT_TIMEOUT        => 15,
        ]);
        $r = json_decode(curl_exec($ch), true);
        curl_close($ch);
        if (empty($r['errors'])) {
            saveImapPass($currentEmail, $newPass);
            $imapPass = $newPass;
        }
    }
    if ($imapPass) {
        $imap        = new ImapClient($currentEmail, $imapPass);
        $isConnected = $imap->testConnection();
        if ($isConnected) {
            $folders      = $imap->listFolders();
            if (empty($folders)) $folders = ['INBOX', 'INBOX.Sent', 'INBOX.Drafts', 'INBOX.Trash', 'INBOX.Junk'];
            $folderCounts = $imap->getFolderCounts($folders);
            if ($search) {
                $seqs    = $imap->searchMessages($folder, $search);
                $mailData = ['messages' => [], 'total' => count($seqs), 'unseen' => 0];
                foreach (array_slice(array_reverse($seqs), ($page-1)*20, 20) as $seq) {
                    $r = $imap->getMessageBySeq($folder, $seq);
                    if ($r['success']) { $p = $imap->parseRawMessage($r['raw']); $p['seq'] = $seq; $mailData['messages'][] = $p; }
                }
            } else {
                $mailData = $imap->getFolderMessages($folder, $page);
            }
            if ($viewSeq) {
                $r = $imap->getMessageBySeq($folder, $viewSeq);
                if ($r['success']) {
                    $parsed        = $imap->parseRawMessage($r['raw']);
                    $parsed['seq'] = $viewSeq;
                    $openMessage   = $parsed;
                }
            }
        } else {
            unset($_SESSION['imap_credentials'][$currentEmail]);
            $err = 'IMAP connection failed. Please re-enter password.';
        }
    }
}

$perPage    = 20;
$totalPages = $mailData['total'] > 0 ? ceil($mailData['total'] / $perPage) : 1;

$pageTitle  = 'Mailbox — cPanel Manager';
$activePage = 'email-mailbox';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Mailbox</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">IMAP: <?= htmlspecialchars($imapCfg['host'] ?? 'not configured') ?>:<?= $imapCfg['port'] ?? 993 ?></p>
  </div>
  <div class="flex items-center gap-2">
    <?php if ($isConnected): ?>
    <button onclick="document.getElementById('modal-compose').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
      <i data-lucide="pencil" class="h-4 w-4"></i> Compose
    </button>
    <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 dark:bg-green-900/20 px-3 py-1.5 text-xs font-medium text-green-700 dark:text-green-400">
      <i data-lucide="check-circle" class="h-3.5 w-3.5"></i> <?= htmlspecialchars($currentEmail) ?>
    </span>
    <?php endif; ?>
  </div>
</div>

<?php if ($msg): ?>
<div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center gap-2">
  <i data-lucide="check-circle" class="h-4 w-4"></i> <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>
<?php if ($err): ?>
<div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400 flex items-center gap-2">
  <i data-lucide="alert-circle" class="h-4 w-4"></i> <?= htmlspecialchars($err) ?>
</div>
<?php endif; ?>

<?php if (!$imapCfg['host']): ?>
<div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-900/20 p-6 text-center">
  <i data-lucide="settings" class="h-10 w-10 text-amber-500 mx-auto mb-3"></i>
  <h2 class="text-base font-semibold text-gray-900 dark:text-white mb-2">IMAP Not Configured</h2>
  <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Go to Settings and save your IMAP host.</p>
  <a href="../settings.php" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
    <i data-lucide="settings" class="h-4 w-4"></i> Go to Settings
  </a>
</div>

<?php elseif (!$isConnected): ?>
<div class="rounded-xl border border-red-200 dark:border-red-800 bg-red-50 dark:bg-red-900/20 p-5 flex items-start gap-3">
  <i data-lucide="alert-circle" class="h-5 w-5 text-red-500 flex-shrink-0 mt-0.5"></i>
  <div class="flex-1">
    <p class="text-sm font-medium text-red-800 dark:text-red-300">Cannot connect to <strong><?= htmlspecialchars($currentEmail) ?></strong></p>
    <p class="text-xs text-red-600 dark:text-red-400 mt-1">The master cPanel password didn't work for this account. Enter the correct password below to save it.</p>
  </div>
</div>
<div class="max-w-md mx-auto mt-4">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-6">
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="save_pass" />
      <input type="hidden" name="email" value="<?= htmlspecialchars($currentEmail) ?>" />
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300"><?= htmlspecialchars($currentEmail) ?></label>
        <input type="password" name="password" placeholder="Email account password" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" required autofocus />
      </div>
      <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 flex items-center justify-center gap-2">
        <i data-lucide="log-in" class="h-4 w-4"></i> Save & Connect
      </button>
    </form>
  </div>
</div>

<?php else: ?>
<div class="grid gap-4 lg:grid-cols-5" style="min-height:600px">

  <!-- Sidebar -->
  <div class="lg:col-span-1 space-y-3">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-3">
      <p class="text-xs font-medium text-gray-400 mb-2">Account</p>
      <form method="get" action="" class="space-y-2">
        <select id="sb-group" onchange="filterEmails('sb-email',this.value)" class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <?php foreach (array_keys($groupedAccounts) as $grp): ?>
          <option value="<?= htmlspecialchars($grp) ?>" <?= $grp === $currentGroup ? 'selected' : '' ?>><?= htmlspecialchars($grp) ?> (<?= count($groupedAccounts[$grp]) ?>)</option>
          <?php endforeach; ?>
        </select>
        <select id="sb-email" name="account" class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 text-sm text-gray-800 dark:text-gray-100 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
          <?php foreach ($groupedAccounts[$currentGroup] as $acc): ?>
          <option value="<?= htmlspecialchars($acc) ?>" <?= $acc === $currentEmail ? 'selected' : '' ?>><?= htmlspecialchars($acc) ?></option>
          <?php endforeach; ?>
        </select>
        <input type="hidden" name="folder" value="INBOX">
        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 px-3 py-2 text-sm font-medium text-white">
          <i data-lucide="plug" class="h-4 w-4"></i> Connect
        </button>
      </form>

    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-3">
      <p class="text-xs font-medium text-gray-400 mb-2">Folders</p>
      <?php
      $folderIcons = ['INBOX'=>'inbox','INBOX.Sent'=>'send','INBOX.Drafts'=>'file-edit','INBOX.Trash'=>'trash-2','INBOX.Junk'=>'alert-triangle','INBOX.spam'=>'alert-triangle','INBOX.Archive'=>'archive'];
      foreach ($folders as $f):
        $icon     = $folderIcons[$f] ?? 'folder';
        $label    = $f === 'INBOX' ? 'Inbox' : str_replace('INBOX.', '', $f);
        $isActive = $f === $folder;
        $fUnseen  = $folderCounts[$f]['unseen'] ?? 0;
        $fTotal   = $folderCounts[$f]['total']  ?? 0;
      ?>
      <a href="?account=<?= urlencode($currentEmail) ?>&folder=<?= urlencode($f) ?>"
         class="flex items-center gap-2 rounded-lg px-2 py-2 text-sm <?= $isActive ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-medium' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700' ?>">
        <i data-lucide="<?= $icon ?>" class="h-4 w-4 flex-shrink-0"></i>
        <span class="flex-1 truncate"><?= htmlspecialchars($label) ?></span>
        <?php if ($fUnseen > 0): ?>
          <span class="rounded-full bg-blue-500 text-white text-xs px-1.5 py-0.5"><?= $fUnseen ?></span>
        <?php elseif ($fTotal > 0): ?>
          <span class="rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 text-xs px-1.5 py-0.5"><?= $fTotal ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Message List -->
  <div class="lg:col-span-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm flex flex-col overflow-hidden" style="min-height:0">
    <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between gap-2">
      <span class="text-sm font-semibold text-gray-900 dark:text-white flex-shrink-0"><?= htmlspecialchars($folder === 'INBOX' ? 'Inbox' : str_replace('INBOX.', '', $folder)) ?></span>
      <form method="get" class="flex-1 flex items-center gap-1">
        <input type="hidden" name="account" value="<?= htmlspecialchars($currentEmail) ?>">
        <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
        <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search..." class="h-7 flex-1 rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-2 text-xs outline-none focus:border-blue-400">
        <button class="h-7 px-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 hover:bg-gray-200 text-xs"><i data-lucide="search" class="h-3.5 w-3.5"></i></button>
      </form>
      <span class="text-xs text-gray-400 flex-shrink-0"><?= $mailData['total'] ?></span>
    </div>
    <div class="flex-1 overflow-y-auto divide-y divide-gray-100 dark:divide-gray-700">
      <?php if (empty($mailData['messages'])): ?>
      <div class="p-8 text-center text-sm text-gray-400">
        <i data-lucide="inbox" class="h-8 w-8 mx-auto mb-2 text-gray-300"></i> No messages.
      </div>
      <?php endif; ?>
      <?php foreach ($mailData['messages'] as $m):
        $isOpen = $viewSeq === $m['seq'];
      ?>
      <a href="?account=<?= urlencode($currentEmail) ?>&folder=<?= urlencode($folder) ?>&msg=<?= $m['seq'] ?>&page=<?= $page ?>"
         class="block p-3 <?= $isOpen ? 'bg-blue-50 dark:bg-blue-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700/50' ?>">
        <div class="flex items-center justify-between mb-0.5">
          <span class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate"><?= htmlspecialchars($m['from']) ?></span>
          <span class="text-xs text-gray-400 flex-shrink-0 ml-2"><?= $m['date'] ? date('M d', strtotime($m['date'])) : '' ?></span>
        </div>
        <p class="text-xs text-gray-600 dark:text-gray-400 truncate"><?= htmlspecialchars($m['subject']) ?></p>
      </a>
      <?php endforeach; ?>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="flex items-center justify-between border-t border-gray-100 dark:border-gray-700 px-4 py-2">
      <span class="text-xs text-gray-400">Page <?= $page ?> of <?= $totalPages ?></span>
      <div class="flex gap-1">
        <?php if ($page > 1): ?>
        <a href="?account=<?= urlencode($currentEmail) ?>&folder=<?= urlencode($folder) ?>&page=<?= $page-1 ?>" class="h-7 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-2 text-xs flex items-center">Prev</a>
        <?php endif; ?>
        <?php if ($page < $totalPages): ?>
        <a href="?account=<?= urlencode($currentEmail) ?>&folder=<?= urlencode($folder) ?>&page=<?= $page+1 ?>" class="h-7 rounded border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-2 text-xs flex items-center">Next</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Message View -->
  <div class="lg:col-span-2 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm flex flex-col overflow-hidden">
    <?php if ($openMessage): ?>
    <div class="flex flex-wrap items-center gap-1.5 p-3 border-b border-gray-100 dark:border-gray-700">
      <a href="?account=<?= urlencode($currentEmail) ?>&folder=<?= urlencode($folder) ?>&page=<?= $page ?>"
         class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-700">
        <i data-lucide="arrow-left" class="h-4 w-4"></i>
      </a>
      <div class="w-px h-5 bg-gray-200 dark:bg-gray-600 mx-0.5"></div>
      <!-- Reply -->
      <button onclick="openReply('<?= htmlspecialchars(addslashes($openMessage['from'])) ?>','<?= htmlspecialchars(addslashes($openMessage['subject'])) ?>')" class="inline-flex items-center gap-1.5 h-8 rounded-lg bg-blue-600 hover:bg-blue-700 px-3 text-xs font-medium text-white">
        <i data-lucide="reply" class="h-3.5 w-3.5"></i> Reply
      </button>
      <!-- Forward -->
      <button onclick="openForward('<?= htmlspecialchars(addslashes($openMessage['subject'])) ?>','<?= htmlspecialchars(addslashes($openMessage['body'] ?? '')) ?>')" class="inline-flex items-center gap-1.5 h-8 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-50 px-3 text-xs font-medium text-gray-700 dark:text-gray-200">
        <i data-lucide="forward" class="h-3.5 w-3.5"></i> Forward
      </button>
      <!-- Mark read/unread -->
      <form method="POST" class="inline">
        <input type="hidden" name="imap_action" value="flag">
        <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
        <input type="hidden" name="seq" value="<?= $viewSeq ?>">
        <input type="hidden" name="flag" value="Seen">
        <input type="hidden" name="set" value="0">
        <button type="submit" class="inline-flex items-center gap-1.5 h-8 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 hover:bg-gray-50 px-3 text-xs font-medium text-gray-700 dark:text-gray-200" title="Mark Unread">
          <i data-lucide="mail" class="h-3.5 w-3.5"></i>
        </button>
      </form>
      <!-- Move to folder -->
      <form method="POST" class="inline flex items-center gap-1">
        <input type="hidden" name="imap_action" value="move">
        <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
        <input type="hidden" name="seq" value="<?= $viewSeq ?>">
        <select name="dest" onchange="this.form.submit()" class="h-8 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-2 text-xs outline-none">
          <option value="">Move to...</option>
          <?php foreach ($folders as $f): ?>
          <?php if ($f !== $folder): ?>
          <option value="<?= htmlspecialchars($f) ?>"><?= htmlspecialchars($f === 'INBOX' ? 'Inbox' : str_replace('INBOX.', '', $f)) ?></option>
          <?php endif; ?>
          <?php endforeach; ?>
        </select>
      </form>
      <!-- Delete -->
      <form method="POST" class="inline" onsubmit="return confirm('Move to trash?')">
        <input type="hidden" name="imap_action" value="delete">
        <input type="hidden" name="folder" value="<?= htmlspecialchars($folder) ?>">
        <input type="hidden" name="seq" value="<?= $viewSeq ?>">
        <button type="submit" class="inline-flex items-center gap-1.5 h-8 rounded-lg border border-red-200 dark:border-red-800 bg-white dark:bg-gray-700 hover:bg-red-50 px-3 text-xs font-medium text-red-600 dark:text-red-400">
          <i data-lucide="trash-2" class="h-3.5 w-3.5"></i> Delete
        </button>
      </form>
    </div>
    <div class="flex-1 overflow-y-auto p-5">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-3"><?= htmlspecialchars($openMessage['subject']) ?></h2>
      <div class="flex items-start gap-3 mb-4 pb-4 border-b border-gray-100 dark:border-gray-700">
        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-700 font-semibold text-sm flex-shrink-0">
          <?= strtoupper(substr(strip_tags($openMessage['from']), 0, 1)) ?>
        </div>
        <div>
          <p class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= htmlspecialchars($openMessage['from']) ?></p>
          <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($openMessage['date']) ?></p>
          <?php if (!empty($openMessage['to'])): ?>
          <p class="text-xs text-gray-400">To: <?= htmlspecialchars($openMessage['to']) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <?php
        $rawBody = $openMessage['raw'] ?? '';
        $lines = explode("\n", $rawBody);
        $headers = []; $bodyStart = 0; $prevKey = '';
        foreach ($lines as $i => $line) {
            if (trim($line) === '') { $bodyStart = $i + 1; break; }
            if (preg_match('/^([A-Za-z-]+):\s*(.*)$/i', $line, $m)) { $prevKey = strtolower($m[1]); $headers[$prevKey] = trim($m[2]); }
            elseif ($prevKey && preg_match('/^\s+(.+)/', $line, $m)) $headers[$prevKey] .= ' ' . trim($m[1]);
        }
        $ct  = $headers['content-type'] ?? 'text/plain';
        $enc = $headers['content-transfer-encoding'] ?? '';
        $rawBodyPart = implode("\n", array_slice($lines, $bodyStart));
        $isHtml = false; $bodyContent = '';
        if (stripos($ct, 'multipart/') !== false && preg_match('/boundary="?([^";]+)"?/i', $ct, $bm)) {
            $parts = preg_split('/--' . preg_quote($bm[1], '/') . '(?:--)?/', $rawBodyPart);
            foreach ($parts as $part) {
                $part = ltrim($part); if (empty($part)) continue;
                $pLines = explode("\n", $part); $pH = []; $pStart = 0;
                foreach ($pLines as $j => $pl) {
                    if (trim($pl) === '') { $pStart = $j + 1; break; }
                    if (preg_match('/^([A-Za-z-]+):\s*(.*)$/i', $pl, $m)) $pH[strtolower($m[1])] = trim($m[2]);
                }
                $pBody = implode("\n", array_slice($pLines, $pStart));
                $pEnc2 = strtolower(trim($pH['content-transfer-encoding'] ?? ''));
                $decoded = $pEnc2 === 'base64' ? base64_decode(str_replace(["\r","\n"], '', $pBody)) : ($pEnc2 === 'quoted-printable' ? quoted_printable_decode($pBody) : $pBody);
                if (stripos($pH['content-type'] ?? '', 'text/html') !== false) { $bodyContent = $decoded; $isHtml = true; break; }
                if (stripos($pH['content-type'] ?? '', 'text/plain') !== false && !$isHtml) $bodyContent = nl2br(htmlspecialchars($decoded));
            }
        } else {
            $encL = strtolower(trim($enc));
            $decoded = $encL === 'base64' ? base64_decode(str_replace(["\r","\n"], '', $rawBodyPart)) : ($encL === 'quoted-printable' ? quoted_printable_decode($rawBodyPart) : $rawBodyPart);
            if (stripos($ct, 'text/html') !== false) { $bodyContent = $decoded; $isHtml = true; }
            else $bodyContent = nl2br(htmlspecialchars($decoded));
        }
      ?>
      <?php if ($isHtml): ?>
      <iframe srcdoc="<?= htmlspecialchars($bodyContent, ENT_QUOTES) ?>"
              sandbox="allow-same-origin"
              class="w-full rounded-lg border border-gray-100 dark:border-gray-700"
              style="min-height:400px;height:600px"
              onload="this.style.height=this.contentDocument.body.scrollHeight+40+'px'"></iframe>
      <?php else: ?>
      <div class="text-sm text-gray-700 dark:text-gray-300 leading-relaxed"><?= $bodyContent ?></div>
      <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="flex-1 flex items-center justify-center text-center p-8">
      <div>
        <i data-lucide="mail-open" class="h-12 w-12 text-gray-200 dark:text-gray-600 mx-auto mb-3"></i>
        <p class="text-sm text-gray-400">Select a message to read</p>
      </div>
    </div>
    <?php endif; ?>
  </div>

</div>
<?php endif; ?>

<!-- Compose Modal -->
<div id="modal-compose" class="hidden fixed inset-0 z-50 flex items-end sm:items-center justify-center">
  <div class="fixed inset-0 bg-black/60" onclick="document.getElementById('modal-compose').classList.add('hidden')"></div>
  <div class="relative z-50 w-full max-w-2xl mx-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-2xl">
    <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100 dark:border-gray-700">
      <h3 id="compose-title" class="text-sm font-semibold text-gray-900 dark:text-white">New Message</h3>
      <button onclick="document.getElementById('modal-compose').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <form method="POST" class="p-5 space-y-3">
      <input type="hidden" name="imap_action" value="send">
      <div>
        <input type="text" value="<?= htmlspecialchars($currentEmail) ?>" readonly class="h-9 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 px-3 text-sm text-gray-500 dark:text-gray-400 outline-none cursor-not-allowed">
      </div>
      <div>
        <input type="email" name="to" id="compose-to" placeholder="To" required class="h-9 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
      </div>
      <div>
        <input type="text" name="subject" id="compose-subject" placeholder="Subject" class="h-9 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
      </div>
      <div>
        <textarea name="body" id="compose-body" rows="10" placeholder="Write your message..." class="w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 py-2 text-sm outline-none focus:border-blue-400 resize-none"></textarea>
      </div>
      <div class="flex justify-between">
        <button type="button" onclick="document.getElementById('modal-compose').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 px-4 py-2 text-sm text-gray-600 dark:text-gray-300">Cancel</button>
        <button type="submit" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
          <i data-lucide="send" class="h-4 w-4"></i> Send
        </button>
      </div>
    </form>
  </div>
</div>

<script>
const _emailGroups = <?= $groupedJson ?>;
function filterEmails(targetId, group) {
    const sel = document.getElementById(targetId);
    const emails = _emailGroups[group] || [];
    sel.innerHTML = emails.map(e => `<option value="${e}">${e}</option>`).join('');
}
function openReply(from, subject) {
    document.getElementById('compose-title').textContent = 'Reply';
    document.getElementById('compose-to').value = from;
    document.getElementById('compose-subject').value = subject.startsWith('Re:') ? subject : 'Re: ' + subject;
    document.getElementById('compose-body').value = '';
    document.getElementById('modal-compose').classList.remove('hidden');
}
function openForward(subject, body) {
    document.getElementById('compose-title').textContent = 'Forward';
    document.getElementById('compose-to').value = '';
    document.getElementById('compose-subject').value = subject.startsWith('Fwd:') ? subject : 'Fwd: ' + subject;
    document.getElementById('compose-body').value = '\n\n---------- Forwarded message ----------\n' + body;
    document.getElementById('modal-compose').classList.remove('hidden');
}
</script>
<?php include '../includes/layout_end.php'; ?>
