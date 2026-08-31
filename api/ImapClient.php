<?php
require_once __DIR__ . '/../includes/auth.php';

class ImapClient {
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $baseUrl;

    public function __construct(string $user, string $pass) {
        $cfg           = getImapConfig();
        $this->host    = $cfg['host'] ?? '';
        $this->port    = (int)($cfg['port'] ?? 993);
        $this->user    = $user;
        $this->pass    = $pass;
        $ssl           = $this->port === 993 ? 'imaps' : 'imap';
        $this->baseUrl = "{$ssl}://{$this->host}:{$this->port}/";
    }

    private function curl(string $url, array $extra = []): array {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERNAME,       $this->user);
        curl_setopt($ch, CURLOPT_PASSWORD,       $this->pass);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT,        20);
        foreach ($extra as $opt => $val) {
            if ($val !== null) curl_setopt($ch, $opt, $val);
        }
        $out   = curl_exec($ch);
        $error = curl_error($ch);
        if ($error) return ['success' => false, 'error' => $error, 'data' => ''];
        return ['success' => true, 'data' => $out ?? ''];
    }

    public function testConnection(): bool {
        $r = $this->curl($this->baseUrl . 'INBOX');
        return $r['success'] && strlen($r['data']) > 0;
    }

    public function getFolderCounts(array $folders): array {
        $counts = [];
        foreach ($folders as $f) {
            $r = $this->curl($this->baseUrl, [
                CURLOPT_CUSTOMREQUEST => 'STATUS "' . $f . '" (MESSAGES UNSEEN)',
            ]);
            $total = $unseen = 0;
            if ($r['success']) {
                if (preg_match('/MESSAGES\s+(\d+)/i', $r['data'], $m)) $total  = (int)$m[1];
                if (preg_match('/UNSEEN\s+(\d+)/i',   $r['data'], $m)) $unseen = (int)$m[1];
            }
            $counts[$f] = ['total' => $total, 'unseen' => $unseen];
        }
        return $counts;
    }

    public function listFolders(): array {
        $r = $this->curl($this->baseUrl, [CURLOPT_CUSTOMREQUEST => 'LIST "" "*"']);
        if (!$r['success']) return [];
        $folders = [];
        foreach (explode("\n", $r['data']) as $line) {
            if (preg_match('/"([^"]+)"\s*$/', $line, $m)) {
                $folders[] = $m[1];
            } elseif (preg_match('/\s(\S+)\s*$/', trim($line), $m) && !empty($m[1])) {
                $name = trim($m[1], '"');
                if ($name && $name !== ')') $folders[] = $name;
            }
        }
        return array_values(array_unique(array_filter($folders)));
    }

    public function getFolderMessages(string $folder = 'INBOX', int $page = 1, int $perPage = 20): array {
        // Get total + unseen via STATUS
        $statusR = $this->curl($this->baseUrl, [
            CURLOPT_CUSTOMREQUEST => 'STATUS "' . $folder . '" (MESSAGES UNSEEN)',
        ]);
        $total = $unseen = 0;
        if ($statusR['success']) {
            if (preg_match('/MESSAGES\s+(\d+)/i', $statusR['data'], $m)) $total  = (int)$m[1];
            if (preg_match('/UNSEEN\s+(\d+)/i',   $statusR['data'], $m)) $unseen = (int)$m[1];
        }

        if ($total === 0) return ['messages' => [], 'total' => 0, 'unseen' => 0];

        $end      = max(1, $total - ($page - 1) * $perPage);
        $start    = max(1, $end - $perPage + 1);
        $messages = [];

        for ($seq = $end; $seq >= $start; $seq--) {
            $r = $this->curl($this->baseUrl . rawurlencode($folder) . "/;MAILINDEX={$seq}");
            if (!$r['success'] || empty($r['data'])) continue;
            $parsed        = $this->parseRawMessage($r['data']);
            $parsed['seq'] = $seq;
            $messages[]    = $parsed;
        }

        return ['messages' => $messages, 'total' => $total, 'unseen' => $unseen];
    }

    public function getMessageBySeq(string $folder, int $seq): array {
        $r = $this->curl($this->baseUrl . rawurlencode($folder) . "/;MAILINDEX={$seq}");
        if (!$r['success']) return ['success' => false, 'error' => $r['error'] ?? ''];
        return ['success' => true, 'raw' => $r['data']];
    }

    public function deleteMessage(string $folder, int $seq): bool {
        $trash = 'INBOX.Trash';
        $this->curl($this->baseUrl . rawurlencode($folder) . "/;MAILINDEX={$seq}", [
            CURLOPT_CUSTOMREQUEST => "COPY $seq \"$trash\"",
        ]);
        $this->curl($this->baseUrl . rawurlencode($folder) . "/;MAILINDEX={$seq}", [
            CURLOPT_CUSTOMREQUEST => "STORE $seq +FLAGS (\\Deleted)",
        ]);
        $this->curl($this->baseUrl . rawurlencode($folder), [
            CURLOPT_CUSTOMREQUEST => 'EXPUNGE',
        ]);
        return true;
    }

    public function moveMessage(string $folder, int $seq, string $dest): bool {
        $this->curl($this->baseUrl . rawurlencode($folder) . "/;MAILINDEX={$seq}", [
            CURLOPT_CUSTOMREQUEST => "COPY $seq \"$dest\"",
        ]);
        $this->curl($this->baseUrl . rawurlencode($folder) . "/;MAILINDEX={$seq}", [
            CURLOPT_CUSTOMREQUEST => "STORE $seq +FLAGS (\\Deleted)",
        ]);
        $this->curl($this->baseUrl . rawurlencode($folder), [
            CURLOPT_CUSTOMREQUEST => 'EXPUNGE',
        ]);
        return true;
    }

    public function setFlag(string $folder, int $seq, string $flag, bool $set): bool {
        $cmd = ($set ? '+' : '-') . "FLAGS (\\$flag)";
        $r = $this->curl($this->baseUrl . rawurlencode($folder) . "/;MAILINDEX={$seq}", [
            CURLOPT_CUSTOMREQUEST => "STORE $seq $cmd",
        ]);
        return $r['success'];
    }

    public function searchMessages(string $folder, string $query): array {
        $r = $this->curl($this->baseUrl . rawurlencode($folder), [
            CURLOPT_CUSTOMREQUEST => 'SEARCH TEXT "' . addslashes($query) . '"',
        ]);
        if (!$r['success']) return [];
        if (preg_match('/\* SEARCH([\d ]*)/i', $r['data'], $m))
            return array_values(array_filter(array_map('intval', explode(' ', trim($m[1])))));
        return [];
    }

    public function appendMessage(string $folder, string $raw): bool {
        $ch = curl_init($this->baseUrl . rawurlencode($folder));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERNAME       => $this->user,
            CURLOPT_PASSWORD       => $this->pass,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_UPLOAD         => true,
            CURLOPT_INFILESIZE     => strlen($raw),
            CURLOPT_READDATA       => fopen('data://text/plain;base64,' . base64_encode($raw), 'r'),
            CURLOPT_TIMEOUT        => 20,
        ]);
        curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        return empty($err);
    }

    public function parseRawMessage(string $raw): array {
        $lines     = explode("\n", $raw);
        $headers   = [];
        $bodyStart = 0;
        $prevKey   = '';

        foreach ($lines as $i => $line) {
            if (trim($line) === '') { $bodyStart = $i + 1; break; }
            if (preg_match('/^([A-Za-z-]+):\s*(.*)$/i', $line, $m)) {
                $prevKey            = strtolower($m[1]);
                $headers[$prevKey]  = trim($m[2]);
            } elseif ($prevKey && preg_match('/^\s+(.+)/', $line, $m)) {
                $headers[$prevKey] .= ' ' . trim($m[1]);
            }
        }

        $body = implode("\n", array_slice($lines, $bodyStart));
        $ct   = $headers['content-type'] ?? 'text/plain';
        $enc  = $headers['content-transfer-encoding'] ?? '';

        // Handle multipart
        if (stripos($ct, 'multipart/') !== false && preg_match('/boundary="?([^";]+)"?/i', $ct, $bm)) {
            $body = $this->extractMultipart($body, $bm[1]);
        } else {
            $body = $this->decodeBody($body, $enc);
            if (stripos($ct, 'text/html') !== false) {
                $body = strip_tags(html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }
        }

        return [
            'from'    => $this->decodeMimeHeader($headers['from']    ?? 'Unknown'),
            'to'      => $this->decodeMimeHeader($headers['to']      ?? ''),
            'subject' => $this->decodeMimeHeader($headers['subject'] ?? '(no subject)'),
            'date'    => $headers['date'] ?? '',
            'body'    => trim($body),
            'raw'     => $raw,
        ];
    }

    private function extractMultipart(string $body, string $boundary): string {
        $parts = preg_split('/--' . preg_quote($boundary, '/') . '(?:--)?/', $body);
        $text  = '';
        $html  = '';
        foreach ($parts as $part) {
            $part = ltrim($part);
            if (empty($part)) continue;
            $lines    = explode("\n", $part);
            $pHeaders = [];
            $pBody    = '';
            $pStart   = 0;
            foreach ($lines as $i => $line) {
                if (trim($line) === '') { $pStart = $i + 1; break; }
                if (preg_match('/^([A-Za-z-]+):\s*(.*)$/i', $line, $m))
                    $pHeaders[strtolower($m[1])] = trim($m[2]);
            }
            $pBody = implode("\n", array_slice($lines, $pStart));
            $pCt   = $pHeaders['content-type'] ?? '';
            $pEnc  = $pHeaders['content-transfer-encoding'] ?? '';
            $pBody = $this->decodeBody($pBody, $pEnc);
            if (stripos($pCt, 'text/plain') !== false) $text = $pBody;
            elseif (stripos($pCt, 'text/html') !== false) $html = $pBody;
        }
        if ($text) return $text;
        if ($html)  return strip_tags(html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return '';
    }

    private function decodeBody(string $body, string $encoding): string {
        $enc = strtolower(trim($encoding));
        if ($enc === 'base64')           return base64_decode(str_replace(["\r", "\n"], '', $body));
        if ($enc === 'quoted-printable') return quoted_printable_decode($body);
        return $body;
    }

    public function sendMail(string $from, string $to, string $subject, string $body, string $smtpHost, int $smtpPort, string $smtpPass): array {
        $boundary = md5(uniqid());
        $raw  = "From: $from\r\n";
        $raw .= "To: $to\r\n";
        $raw .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $raw .= "MIME-Version: 1.0\r\n";
        $raw .= "Content-Type: text/html; charset=UTF-8\r\n";
        $raw .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $raw .= chunk_split(base64_encode($body));

        $ssl  = $smtpPort === 465 ? 'smtps' : 'smtp';
        $url  = "{$ssl}://{$smtpHost}:{$smtpPort}";
        $ch   = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER  => true,
            CURLOPT_USERNAME        => $from,
            CURLOPT_PASSWORD        => $smtpPass,
            CURLOPT_SSL_VERIFYPEER  => false,
            CURLOPT_SSL_VERIFYHOST  => false,
            CURLOPT_MAIL_FROM       => "<$from>",
            CURLOPT_MAIL_RCPT       => ["<$to>"],
            CURLOPT_READDATA        => fopen('data://text/plain,' . urlencode($raw), 'r'),
            CURLOPT_UPLOAD          => true,
            CURLOPT_TIMEOUT         => 20,
        ]);
        $err = curl_error($ch);
        curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($err) return ['success' => false, 'error' => $err];
        return ['success' => true];
    }

    public function decodeMimeHeader(string $str): string {
        if (preg_match_all('/=\?([^?]+)\?([BbQq])\?([^?]*)\?=/', $str, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $decoded = strtolower($m[2]) === 'b'
                    ? base64_decode($m[3])
                    : quoted_printable_decode(str_replace('_', ' ', $m[3]));
                $decoded = mb_convert_encoding($decoded, 'UTF-8', $m[1]);
                $str     = str_replace($m[0], $decoded, $str);
            }
        }
        return $str;
    }
}
