<?php
require_once __DIR__ . '/../includes/auth.php';

class CpanelClient {
    private string $host;
    private int    $port;
    private string $username;
    private string $token;
    private int    $index;

    public function __construct(int $index = 0) {
        $this->index    = $index;
        $cfg = getCpanelConfig($index);
        $this->host     = $cfg['host']      ?? '';
        $this->port     = (int)($cfg['port'] ?? 2083);
        $this->username = $cfg['username']  ?? '';
        $this->token    = $cfg['api_token'] ?? '';
    }

    public function call(string $module, string $function, array $params = []): array {
        $url = "https://{$this->host}:{$this->port}/execute/{$module}/{$function}";
        if ($params) $url .= '?' . http_build_query($params);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => [
                "Authorization: cpanel {$this->username}:{$this->token}",
            ],
        ]);

        $start    = microtime(true);
        $response = curl_exec($ch);
        $duration = round((microtime(true) - $start) * 1000) . 'ms';
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) return $this->fail("cURL error: $error", $duration);
        if ($httpCode === 401) return $this->fail('Unauthorized — check API token', $duration);
        if ($httpCode === 403) return $this->fail('Forbidden — insufficient permissions', $duration);

        $data = json_decode($response, true);
        if (!$data) return $this->fail('Invalid JSON response', $duration);

        $this->logRequest($module, $function, $httpCode, $duration, empty($data['errors']));
        return ['success' => true, 'data' => $data['data'] ?? [], 'errors' => $data['errors'] ?? [], 'warnings' => $data['warnings'] ?? [], 'duration' => $duration];
    }

    public function testConnection(): array {
        return $this->call('DomainInfo', 'primary_domain');
    }

    public function getWebmailSsoUrl(string $email): ?string {
        $cfg  = getCpanelConfig($this->index);
        $pass = $cfg['cpanel_password'] ?? '';
        if (!$pass) return null;

        // Step 1: Login to cPanel to get session token + cookies
        $ch = curl_init("https://{$this->host}:{$this->port}/login/?login_only=1");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['user' => $this->username, 'pass' => $pass]),
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
        if (!$secToken) return null;

        preg_match_all('/^Set-Cookie:\s*([^;\r\n]+)/mi', $headers, $cm);
        $cookieStr = implode('; ', array_filter($cm[1] ?? [], fn($c) => strpos($c, 'cpsession=') === 0));

        // Step 2: Fetch webmailform.html to get session token
        $ch2 = curl_init("https://{$this->host}:{$this->port}/{$secToken}/frontend/jupiter/mail/webmailform.html?user=" . urlencode($email));
        curl_setopt_array($ch2, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIE         => $cookieStr,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $html = curl_exec($ch2);
        curl_close($ch2);

        if (preg_match("/name=['\"]session['\"]\s+value=['\"]([^'\"]+)['\"]/", $html, $m)) {
            return "https://{$this->host}:2096/?login=1&user=" . urlencode($email) . '&session=' . urlencode($m[1]);
        }
        return null;
    }

    private function fail(string $message, string $duration = '0ms'): array {
        return ['success' => false, 'data' => [], 'errors' => [$message], 'warnings' => [], 'duration' => $duration];
    }

    private function logRequest(string $module, string $function, int $status, string $duration, bool $success): void {
        $logFile = __DIR__ . '/../logs/api.log';
        if (!is_dir(dirname($logFile))) mkdir(dirname($logFile), 0755, true);
        $line = implode(' | ', [date('Y-m-d H:i:s'), $module, $function, $status, $duration, $success ? 'OK' : 'FAIL']) . PHP_EOL;
        file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
    }
}
