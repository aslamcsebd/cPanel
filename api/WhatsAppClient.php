<?php
/**
 * WhatsApp Notification Client
 *
 * Supports three providers:
 *   callmebot  — Free, no account. User must send "I allow callmebot to send me messages"
 *                to +34 644 59 73 30 on WhatsApp before first use.
 *   ultramsg   — Paid/trial. Needs an UltraMsg instance ID + token.
 *   meta       — Meta Cloud API (WhatsApp Business). Needs phone number ID + access token.
 */
class WhatsAppClient {

    private array $cfg;

    public function __construct(array $cfg) {
        $this->cfg = $cfg;
    }

    /**
     * Send a WhatsApp message.
     *
     * @param  string $message  The text to send.
     * @return array  ['success' => bool, 'error' => string]
     */
    public function send(string $message): array {
        $provider = $this->cfg['provider'] ?? 'callmebot';

        switch ($provider) {
            case 'callmebot':
                return $this->sendCallmebot($message);
            case 'ultramsg':
                return $this->sendUltramsg($message);
            case 'meta':
                return $this->sendMeta($message);
            default:
                return ['success' => false, 'error' => "Unknown provider: $provider"];
        }
    }

    // ── CallMeBot ──────────────────────────────────────────────────────────────
    private function sendCallmebot(string $message): array {
        $phone  = $this->cfg['phone'] ?? '';
        $apikey = $this->cfg['callmebot_apikey'] ?? '';

        if (!$phone || !$apikey) {
            return ['success' => false, 'error' => 'CallMeBot: phone and apikey are required.'];
        }

        $url = 'https://api.callmebot.com/whatsapp.php?' . http_build_query([
            'phone'  => $phone,
            'text'   => $message,
            'apikey' => $apikey,
        ]);

        return $this->httpGet($url);
    }

    // ── UltraMsg ───────────────────────────────────────────────────────────────
    private function sendUltramsg(string $message): array {
        $instance = $this->cfg['ultramsg_instance'] ?? '';
        $token    = $this->cfg['ultramsg_token']    ?? '';
        $to       = $this->cfg['phone']             ?? '';

        if (!$instance || !$token || !$to) {
            return ['success' => false, 'error' => 'UltraMsg: instance, token, and phone are required.'];
        }

        // Ensure phone has country code with +
        if ($to[0] !== '+') $to = '+' . $to;

        $url  = "https://api.ultramsg.com/{$instance}/messages/chat";
        $body = http_build_query(['token' => $token, 'to' => $to, 'body' => $message]);

        return $this->httpPost($url, $body, 'application/x-www-form-urlencoded');
    }

    // ── Meta Cloud API ─────────────────────────────────────────────────────────
    private function sendMeta(string $message): array {
        $phoneId = $this->cfg['meta_phone_id']    ?? '';
        $token   = $this->cfg['meta_token']       ?? '';
        $to      = $this->cfg['phone']            ?? '';

        if (!$phoneId || !$token || !$to) {
            return ['success' => false, 'error' => 'Meta: phone_id, token, and to-phone are required.'];
        }

        if ($to[0] !== '+') $to = '+' . $to;
        $to = ltrim($to, '+'); // Meta uses numbers without +

        $url  = "https://graph.facebook.com/v19.0/{$phoneId}/messages";
        $body = json_encode([
            'messaging_product' => 'whatsapp',
            'to'                => $to,
            'type'              => 'text',
            'text'              => ['body' => $message],
        ]);

        return $this->httpPost($url, $body, 'application/json', "Authorization: Bearer $token");
    }

    // ── HTTP helpers ───────────────────────────────────────────────────────────
    private function httpGet(string $url): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'cPanel-Manager/1.0',
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err)           return ['success' => false, 'error' => $err];
        if ($code >= 400)   return ['success' => false, 'error' => "HTTP $code: $resp"];
        return ['success' => true, 'response' => $resp];
    }

    private function httpPost(string $url, string $body, string $contentType, string ...$extraHeaders): array {
        $headers = ["Content-Type: $contentType", "Content-Length: " . strlen($body)];
        foreach ($extraHeaders as $h) $headers[] = $h;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_USERAGENT      => 'cPanel-Manager/1.0',
        ]);
        $resp = curl_exec($ch);
        $err  = curl_error($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err)           return ['success' => false, 'error' => $err];
        if ($code >= 400)   return ['success' => false, 'error' => "HTTP $code: $resp"];
        return ['success' => true, 'response' => $resp];
    }

    /**
     * Quick connectivity test — sends a test message.
     */
    public function test(): array {
        return $this->send('✅ Test message from cPanel Manager — WhatsApp alerts are working!');
    }
}
