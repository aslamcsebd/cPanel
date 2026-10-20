<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
require_once '../api/CpanelClient.php';

header('Content-Type: application/json');

// ── Helpers ───────────────────────────────────────────────────────────────────

function incrementSerial(string $serial): string {
    $chars = str_split($serial);
    $carry = true;
    for ($i = count($chars) - 1; $i >= 0 && $carry; $i--) {
        if ($chars[$i] === 'z') {
            $chars[$i] = 'a';
        } else {
            $chars[$i] = chr(ord($chars[$i]) + 1);
            $carry = false;
        }
    }
    if ($carry) array_unshift($chars, 'a');
    return implode('', $chars);
}

function getNextSerial(array $existingSerials): string {
    if (empty($existingSerials)) return 'a';
    usort($existingSerials, function ($a, $b) {
        if (strlen($a) !== strlen($b)) return strlen($a) - strlen($b);
        return strcmp($a, $b);
    });
    return incrementSerial(end($existingSerials));
}

/**
 * Search config.email_passwords for today's serials on the given domain.
 * No cPanel API call needed — the local JSON is the source of truth.
 */
function getSerialsFromConfig(array $emailPasswords, string $datePrefix, string $domain): array {
    $serials = [];
    $pattern = '/^' . preg_quote($datePrefix, '/') . '([a-z]+)@' . preg_quote($domain, '/') . '$/i';
    foreach (array_keys($emailPasswords) as $email) {
        if (preg_match($pattern, $email, $m)) {
            $serials[] = strtolower($m[1]);
        }
    }
    return $serials;
}

$datePrefix = date('ymd');  // e.g. 261020

// ── Preview (GET ?action=preview&domain=…) ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'preview') {
    $domain = trim($_GET['domain'] ?? '');
    if (empty($domain)) {
        exit(json_encode(['next_email' => null, 'error' => 'Domain required']));
    }

    $config  = readConfig();
    $serials = getSerialsFromConfig($config['email_passwords'] ?? [], $datePrefix, $domain);
    $next    = getNextSerial($serials);

    exit(json_encode(['next_email' => $datePrefix . $next . '@' . $domain]));
}

// ── Create (POST) ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'error' => 'Method not allowed']));
}

$domain   = trim($_POST['domain']   ?? '');
$password = $_POST['password']      ?? '';
$quota    = (int)($_POST['quota']   ?? 50);

if (empty($domain) || empty($password)) {
    exit(json_encode(['success' => false, 'error' => 'Domain and password are required']));
}
if (strlen($password) < 8) {
    exit(json_encode(['success' => false, 'error' => 'Password must be at least 8 characters']));
}

// Read config — no API call needed for serial lookup
$config  = readConfig();
$serials = getSerialsFromConfig($config['email_passwords'] ?? [], $datePrefix, $domain);
$serial  = getNextSerial($serials);

$username  = $datePrefix . $serial;
$fullEmail = $username . '@' . $domain;

// ── Single cPanel API call: create the mailbox ────────────────────────────────
$client = new CpanelClient();

for ($attempt = 0; $attempt < 5; $attempt++) {
    $r      = $client->call('Email', 'add_pop', [
        'email'    => $username,
        'domain'   => $domain,
        'password' => $password,
        'quota'    => $quota,
    ]);
    $errors = $r['errors'] ?? [];

    if ($r['success'] && empty($errors)) {
        // Save to config so next call can use it without API
        $config['email_passwords'][$fullEmail] = $password;
        writeConfig($config);

        exit(json_encode([
            'success'  => true,
            'email'    => $fullEmail,
            'username' => $username,
            'serial'   => $serial,
        ]));
    }

    $errMsg = implode(' ', $errors);

    // Conflict: username already exists on cPanel — advance and retry
    if (stripos($errMsg, 'already exists') !== false || stripos($errMsg, 'duplicate') !== false) {
        // Also save the conflicting one so we don't clash again next time
        if (!isset($config['email_passwords'][$fullEmail])) {
            $config['email_passwords'][$fullEmail] = '';
            writeConfig($config);
        }
        $serial    = incrementSerial($serial);
        $username  = $datePrefix . $serial;
        $fullEmail = $username . '@' . $domain;
        continue;
    }

    // Any other cPanel error — stop immediately
    exit(json_encode(['success' => false, 'error' => $errMsg ?: 'Failed to create mailbox']));
}

exit(json_encode(['success' => false, 'error' => 'Unable to create mailbox after 5 attempts']));
