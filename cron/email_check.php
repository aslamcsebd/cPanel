#!/usr/bin/env php
<?php
/**
 * Email → WhatsApp Notification Checker
 *
 * Run this via cron every minute (or however often you want alerts):
 *   * * * * * /usr/bin/php /var/www/html/cpanel/cron/email_check.php >> /var/www/html/cpanel/logs/whatsapp.log 2>&1
 *
 * Or via cPanel Cron Jobs UI:
 *   Command: /usr/bin/php /var/www/html/cpanel/cron/email_check.php
 *   Interval: Every minute (or every 5 minutes to reduce load)
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────────
define('CRON_MODE', true); // Prevents auth.php from redirecting

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../api/ImapClient.php';
require_once __DIR__ . '/../api/WhatsAppClient.php';

// ── Load config ────────────────────────────────────────────────────────────────
$config = readConfig();
$wa     = $config['whatsapp'] ?? [];

// Skip if not configured or disabled
if (empty($wa['enabled']) || empty($wa['phone'])) {
    log_msg('WhatsApp notifications not configured or disabled. Skipping.');
    exit(0);
}

// Determine which accounts to monitor
$monitorAccounts = $wa['monitor_accounts'] ?? [];
$allPasswords    = $config['email_passwords'] ?? [];

if (empty($monitorAccounts)) {
    // Default: monitor all accounts that have saved passwords
    $monitorAccounts = array_keys($allPasswords);
}

if (empty($monitorAccounts)) {
    log_msg('No email accounts to monitor.');
    exit(0);
}

// ── WhatsApp client ────────────────────────────────────────────────────────────
$waClient = new WhatsAppClient($wa);

// ── State tracking (persisted in config.json) ─────────────────────────────────
$state = $config['whatsapp_state'] ?? [];

$newMessagesFound = false;

foreach ($monitorAccounts as $email) {
    $pass = $allPasswords[$email] ?? null;
    if (!$pass) {
        log_msg("[$email] No password saved — skipping.");
        continue;
    }

    try {
        $imap = new ImapClient($email, $pass);
        if (!$imap->testConnection()) {
            log_msg("[$email] IMAP connection failed — skipping.");
            continue;
        }

        // Get current folder counts
        $counts        = $imap->getFolderCounts(['INBOX']);
        $currentTotal  = $counts['INBOX']['total']  ?? 0;
        $currentUnseen = $counts['INBOX']['unseen'] ?? 0;

        // Previous state for this account
        $prev          = $state[$email] ?? ['last_total' => 0, 'last_notified_seq' => 0];
        $lastTotal     = (int)($prev['last_total'] ?? 0);

        log_msg("[$email] Total: $currentTotal, Unseen: $currentUnseen, Last known total: $lastTotal");

        if ($currentTotal > $lastTotal) {
            // There are new messages since we last checked
            $newCount = $currentTotal - $lastTotal;
            log_msg("[$email] $newCount new message(s) detected!");

            // Fetch the newest messages (only the new ones, max 5 to avoid spam)
            $fetchCount = min($newCount, 5);
            $mailData   = $imap->getFolderMessages('INBOX', 1, $fetchCount);
            $messages   = $mailData['messages'] ?? [];

            foreach ($messages as $msg) {
                $from    = $msg['from']    ?? 'Unknown';
                $subject = $msg['subject'] ?? '(no subject)';
                $date    = $msg['date']    ?? '';
                $seq     = $msg['seq']     ?? 0;

                // Skip if we already notified for this sequence number
                if ($seq > 0 && $seq <= ($prev['last_notified_seq'] ?? 0)) {
                    continue;
                }

                // Build WhatsApp message
                $text = build_notification($email, $from, $subject, $date);

                log_msg("[$email] Sending WhatsApp for seq=$seq: $subject");
                $result = $waClient->send($text);

                if ($result['success']) {
                    log_msg("[$email] WhatsApp sent OK.");
                    $newMessagesFound = true;
                } else {
                    log_msg("[$email] WhatsApp send FAILED: " . ($result['error'] ?? 'unknown'));
                }

                // Small delay between messages to avoid rate limits
                if ($fetchCount > 1) usleep(500000); // 0.5s
            }
        }

        // Update state with current total and highest seq number
        $highestSeq = 0;
        if (!empty($messages)) {
            foreach ($messages as $m) {
                if (($m['seq'] ?? 0) > $highestSeq) $highestSeq = (int)$m['seq'];
            }
        }

        $state[$email] = [
            'last_total'           => $currentTotal,
            'last_unseen'          => $currentUnseen,
            'last_checked'         => date('Y-m-d H:i:s'),
            'last_notified_seq'    => max(
                (int)($prev['last_notified_seq'] ?? 0),
                $highestSeq
            ),
        ];

    } catch (Throwable $e) {
        log_msg("[$email] Exception: " . $e->getMessage());
    }
}

// ── Save updated state back to config ─────────────────────────────────────────
$config['whatsapp_state'] = $state;
writeConfig($config);

log_msg('Check complete. ' . ($newMessagesFound ? 'Notifications sent.' : 'No new emails.'));
exit(0);

// ── Helpers ────────────────────────────────────────────────────────────────────

function build_notification(string $account, string $from, string $subject, string $date): string {
    // Truncate long fields so the WhatsApp message stays readable
    $from    = mb_substr($from,    0, 60);
    $subject = mb_substr($subject, 0, 80);

    $lines   = [];
    $lines[] = '📧 *New Email Alert*';
    $lines[] = '';
    $lines[] = '📬 *To:* ' . $account;
    $lines[] = '👤 *From:* ' . $from;
    $lines[] = '📌 *Subject:* ' . $subject;
    if ($date) $lines[] = '🕐 *Date:* ' . $date;
    $lines[] = '';
    $lines[] = '_Sent by cPanel Manager_';

    return implode("\n", $lines);
}

function log_msg(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
    // Print to stdout (captured by cron redirect)
    echo $line;
    // Also append to dedicated log file
    $logFile = __DIR__ . '/../logs/whatsapp.log';
    file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
}
