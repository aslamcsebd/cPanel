<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('CONFIG_FILE', __DIR__ . '/config.json');

function readConfig(): array {
    if (!file_exists(CONFIG_FILE)) return ['app' => [], 'cpanel' => []];
    $data = json_decode(file_get_contents(CONFIG_FILE), true);
    return is_array($data) ? $data : ['app' => [], 'cpanel' => []];
}

$cfg = readConfig();
$cp = $cfg['cpanels'][0] ?? [];

$host = $cp['host'] ?? '';
$port = (int)($cp['port'] ?? 2083);
$username = $cp['username'] ?? '';
$token = $cp['api_token'] ?? '';

function callApiPost($host, $port, $username, $token, $module, $function, $params = []) {
    $url = "https://{$host}:{$port}/execute/{$module}/{$function}";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => ["Authorization: cpanel {$username}:{$token}"],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['code' => $httpCode, 'response' => json_decode($response, true)];
}

echo "=== Testing filter with flat params ===\n\n";

echo "1. store_filter with rule1/part1 mismatch:\n";
$r = callApiPost($host, $port, $username, $token, 'Email', 'store_filter', [
    'account' => 'amazon1st@aslambd.com',
    'filtername' => 'AWS Filter',
    'rule1' => 'subject',
    'match1' => 'contains',
    'value1' => 'AWS',
    'action1' => 'deliver',
    'destination1' => 'laptop5@aslambd.com'
]);
echo "   Response: " . json_encode($r['response']) . "\n\n";

echo "2. list_filters:\n";
$r = callApiPost($host, $port, $username, $token, 'Email', 'list_filters', ['account' => 'amazon1st@aslambd.com']);
echo "   Response: " . json_encode($r['response']) . "\n\n";

echo "3. Trying Email::list_filter:\n";
$r = callApiPost($host, $port, $username, $token, 'Email', 'list_filter', ['account' => 'amazon1st@aslambd.com']);
echo "   Response: " . json_encode($r['response']) . "\n\n";

echo "4. Trying filter functions from Email::list_filter:\n";
$r = callApiPost($host, $port, $username, $token, 'Email', 'list_filters', ['account' => 'amazon1st@aslambd.com']);
echo "   Full data: " . print_r($r['response'], true) . "\n";
