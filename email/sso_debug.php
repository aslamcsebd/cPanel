<?php
require_once '../includes/auth.php';
require_once '../api/CpanelClient.php';

$cfg  = getCpanelConfig();
$host = $cfg['host'];
$port = $cfg['port'] ?? 2083;
$user = $cfg['username'];
$pass = $cfg['password'] ?? '';

echo "<pre>";

// Step 1: Login
echo "=== STEP 1: cPanel Login ===\n";
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "cURL Error: " . ($curlErr ?: 'none') . "\n";

$body     = substr($raw, $hdrSize);
$headers  = substr($raw, 0, $hdrSize);
$resp     = json_decode($body, true);
$secToken = trim($resp['security_token'] ?? '', '/');

echo "Security Token: " . ($secToken ?: 'NOT FOUND') . "\n";
echo "Response Body: " . substr($body, 0, 300) . "\n\n";

if (!$secToken) { echo "FAILED at Step 1\n</pre>"; exit; }

// Extract cookies
preg_match_all('/^Set-Cookie:\s*([^;\r\n]+)/mi', $headers, $cm);
$cookieStr = implode('; ', $cm[1] ?? []);
echo "Cookies: " . ($cookieStr ?: 'NONE') . "\n\n";

// Step 2: create_webmail_session
echo "=== STEP 2: create_webmail_session ===\n";
$email = $_GET['email'] ?? '';
if (!$email) { echo "Pass ?email=yourmail@domain.com in URL\n</pre>"; exit; }

$url = "https://{$host}:{$port}/{$secToken}/execute/Session/create_webmail_session?" . http_build_query(['login' => $email]);
echo "URL: $url\n";

$ch2 = curl_init($url);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_COOKIE         => $cookieStr,
    CURLOPT_TIMEOUT        => 15,
]);
$resp2Raw = curl_exec($ch2);
$code2    = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
$err2     = curl_error($ch2);
curl_close($ch2);

echo "HTTP Code: $code2\n";
echo "cURL Error: " . ($err2 ?: 'none') . "\n";
echo "Response: " . $resp2Raw . "\n\n";

$resp2   = json_decode($resp2Raw, true);
$session = $resp2['data']['session'] ?? null;

if ($session) {
    $ssoUrl = "https://{$host}:2096/?login=1&user=" . urlencode($email) . "&session=" . urlencode($session);
    echo "=== SUCCESS ===\n";
    echo "SSO URL: $ssoUrl\n";
    echo "\n<a href='$ssoUrl' target='_blank'>Click to open webmail</a>";
} else {
    echo "=== FAILED: No session token in response ===\n";
}

echo "</pre>";
