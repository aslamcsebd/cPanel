<?php
require_once '../includes/auth.php';
requireLogin();

$email = $_GET['email'] ?? '';
if (!$email) { http_response_code(400); exit('Missing email'); }

$cfg  = getCpanelConfig();
$host = $cfg['host'] ?? '';
$port = $cfg['port'] ?? 2083;
$user = $cfg['username'] ?? '';
$pass = $cfg['password'] ?? '';

if (!$pass) {
    header('Location: https://' . $host . ':2096');
    exit;
}

// Step 1: Login to cPanel
$ch = curl_init("https://{$host}:{$port}/login/?login_only=1");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query(['user' => $user, 'pass' => $pass]),
    CURLOPT_HEADER         => true,
    CURLOPT_TIMEOUT        => 15,
]);
$raw     = curl_exec($ch);
$hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

$body     = substr($raw, $hdrSize);
$headers  = substr($raw, 0, $hdrSize);
$resp     = json_decode($body, true);
$secToken = trim($resp['security_token'] ?? '', '/');

if (!$secToken) {
    header('Location: https://' . $host . ':2096');
    exit;
}

// Extract cpsession cookie
preg_match_all('/^Set-Cookie:\s*([^;\r\n]+)/mi', $headers, $cm);
$cookieStr = implode('; ', array_filter($cm[1] ?? [], fn($c) => strpos($c, 'cpsession=') === 0));

// Step 2: Fetch webmailform.html with session cookie
$ch2 = curl_init("https://{$host}:{$port}/{$secToken}/frontend/jupiter/mail/webmailform.html?user=" . urlencode($email));
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_COOKIE         => $cookieStr,
    CURLOPT_TIMEOUT        => 15,
]);
$html = curl_exec($ch2);
curl_close($ch2);

// Step 3: Extract session token and POST to webmail via browser
if (preg_match("/name=['\"]session['\"]\s+value=['\"]([^'\"]+)['\"]/", $html, $m)) {
    $session = $m[1];
?>
<!DOCTYPE html>
<html>
<head><title>Opening Webmail...</title></head>
<body>
<form id="f" method="POST" action="https://<?= htmlspecialchars($host) ?>:2096/">
  <input type="hidden" name="login" value="1">
  <input type="hidden" name="user" value="<?= htmlspecialchars($email) ?>">
  <input type="hidden" name="session" value="<?= htmlspecialchars($session) ?>">
</form>
<script>document.getElementById('f').submit();</script>
</body>
</html>
<?php
} else {
    header('Location: https://' . $host . ':2096');
}
exit;
