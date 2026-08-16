<?php
define('CONFIG_FILE', __DIR__ . '/../config.json');

function readConfig(): array {
    if (!file_exists(CONFIG_FILE)) {
        return ['app' => [], 'cpanel' => []];
    }
    $data = json_decode(file_get_contents(CONFIG_FILE), true);
    return is_array($data) ? $data : ['app' => [], 'cpanel' => []];
}

function writeConfig(array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT);
    $dir  = dirname(CONFIG_FILE);

    if (!is_writable($dir) && !file_exists(CONFIG_FILE)) {
        return false;
    }
    if (file_exists(CONFIG_FILE) && !is_writable(CONFIG_FILE)) {
        return false;
    }
    $result = file_put_contents(CONFIG_FILE, $json) !== false;
    if ($result) chmod(CONFIG_FILE, 0777);
    return $result;
}

function isSetupDone(): bool {
    $cfg = readConfig();
    return !empty($cfg['app']['username']) && !empty($cfg['app']['password']);
}

function isCpanelConfigured(): bool {
    $cfg = readConfig();
    return !empty($cfg['cpanels'][0]['host'])
        && !empty($cfg['cpanels'][0]['username'])
        && !empty($cfg['cpanels'][0]['api_token']);
}

function getActiveServerIndex(): int {
    return (int)(readConfig()['active_server'] ?? 0);
}

function getCpanelConfig(int $index = -1): array {
    $cfg = readConfig();
    if ($index === -1) $index = (int)($cfg['active_server'] ?? 0);
    return $cfg['cpanels'][$index] ?? [];
}

function getCpanelList(): array {
    return readConfig()['cpanels'] ?? [];
}

function getImapConfig(int $index = -1): array {
    $cp = getCpanelConfig($index);
    return [
        'host' => $cp['imap_host'] ?? $cp['host'] ?? '',
        'port' => (int)($cp['imap_port'] ?? 993),
    ];
}

function getSidebarItems(): array {
    $cfg = readConfig();
    if (!empty($cfg['sidebar']) && is_array($cfg['sidebar'])) {
        return $cfg['sidebar'];
    }
    return [
        ['id'=>'dashboard','section'=>'','label'=>'Dashboard','icon'=>'layout-dashboard','href'=>'dashboard.php','enabled'=>true],
        ['id'=>'email-accounts','section'=>'EMAIL','label'=>'Email Accounts','icon'=>'mail','href'=>'email/accounts.php','enabled'=>true],
        ['id'=>'email-mailbox','section'=>'EMAIL','label'=>'Mailbox','icon'=>'inbox','href'=>'email/mailbox.php','enabled'=>true],
        ['id'=>'email-forwarders','section'=>'EMAIL','label'=>'Forwarders','icon'=>'forward','href'=>'email/forwarders.php','enabled'=>true],
        ['id'=>'email-autoresponders','section'=>'EMAIL','label'=>'Auto Responders','icon'=>'bot','href'=>'email/autoresponders.php','enabled'=>true],
        ['id'=>'email-filters','section'=>'EMAIL','label'=>'Filters','icon'=>'filter','href'=>'email/filters.php','enabled'=>true],
        ['id'=>'email-mx','section'=>'EMAIL','label'=>'MX Records','icon'=>'globe','href'=>'email/mx.php','enabled'=>true],
        ['id'=>'email-dkim','section'=>'EMAIL','label'=>'DKIM','icon'=>'shield-check','href'=>'email/dkim.php','enabled'=>true],
        ['id'=>'email-dmarc','section'=>'EMAIL','label'=>'DMARC','icon'=>'shield','href'=>'email/dmarc.php','enabled'=>true],
        ['id'=>'domains','section'=>'DOMAINS','label'=>'Domains','icon'=>'globe-2','href'=>'domains/index.php','enabled'=>true],
        ['id'=>'subdomains','section'=>'DOMAINS','label'=>'Subdomains','icon'=>'layers','href'=>'domains/subdomains.php','enabled'=>true],
        ['id'=>'redirects','section'=>'DOMAINS','label'=>'Redirects','icon'=>'corner-right-up','href'=>'domains/redirects.php','enabled'=>true],
        ['id'=>'dns','section'=>'DOMAINS','label'=>'DNS','icon'=>'network','href'=>'domains/dns.php','enabled'=>true],
        ['id'=>'dnssec','section'=>'DOMAINS','label'=>'DNSSEC','icon'=>'shield-check','href'=>'domains/dnssec.php','enabled'=>true],
        ['id'=>'ssl','section'=>'DOMAINS','label'=>'SSL / TLS','icon'=>'lock','href'=>'domains/ssl.php','enabled'=>true],
        ['id'=>'domains-webvhosts','section'=>'DOMAINS','label'=>'Virtual Hosts','icon'=>'server','href'=>'domains/webvhosts.php','enabled'=>true],
        ['id'=>'databases','section'=>'DATABASE','label'=>'MySQL','icon'=>'database','href'=>'databases/mysql.php','enabled'=>true],
        ['id'=>'databases-pg','section'=>'DATABASE','label'=>'PostgreSQL','icon'=>'database','href'=>'databases/postgresql.php','enabled'=>true],
        ['id'=>'files','section'=>'FILES','label'=>'File Manager','icon'=>'folder-open','href'=>'files/manager.php','enabled'=>true],
        ['id'=>'webdisk','section'=>'FILES','label'=>'Web Disk','icon'=>'hard-drive','href'=>'files/webdisk.php','enabled'=>true],
        ['id'=>'ftp','section'=>'SERVICES','label'=>'FTP Accounts','icon'=>'upload-cloud','href'=>'services/ftp.php','enabled'=>true],
        ['id'=>'cron','section'=>'SERVICES','label'=>'Cron Jobs','icon'=>'clock','href'=>'services/cron.php','enabled'=>true],
        ['id'=>'git','section'=>'SERVICES','label'=>'Git','icon'=>'git-branch','href'=>'services/git.php','enabled'=>true],
        ['id'=>'api-tokens','section'=>'SECURITY','label'=>'API Tokens','icon'=>'key','href'=>'security/tokens.php','enabled'=>true],
        ['id'=>'security','section'=>'SECURITY','label'=>'Security','icon'=>'shield','href'=>'security/index.php','enabled'=>true],
        ['id'=>'security-ssh','section'=>'SECURITY','label'=>'SSH Keys','icon'=>'terminal','href'=>'security/ssh-hosts.php','enabled'=>true],
        ['id'=>'resource-usage','section'=>'SYSTEM','label'=>'Resource Usage','icon'=>'activity','href'=>'system/resources.php','enabled'=>true],
        ['id'=>'bandwidth','section'=>'SYSTEM','label'=>'Bandwidth','icon'=>'bar-chart-2','href'=>'system/bandwidth.php','enabled'=>true],
        ['id'=>'backups','section'=>'SYSTEM','label'=>'Backups','icon'=>'archive','href'=>'system/backups.php','enabled'=>true],
        ['id'=>'feature-status','section'=>'SYSTEM','label'=>'Feature Status','icon'=>'check-circle','href'=>'system/features.php','enabled'=>true],
        ['id'=>'system-wordpress','section'=>'SYSTEM','label'=>'WordPress','icon'=>'layout-dashboard','href'=>'system/wordpress.php','enabled'=>true],
        ['id'=>'system-calendar','section'=>'SYSTEM','label'=>'Calendar & Contacts','icon'=>'calendar','href'=>'system/calendar.php','enabled'=>true],
        ['id'=>'api-explorer','section'=>'DEVELOPER','label'=>'API Explorer','icon'=>'terminal','href'=>'developer/explorer.php','enabled'=>true],
        ['id'=>'api-logs','section'=>'DEVELOPER','label'=>'API Logs','icon'=>'scroll-text','href'=>'developer/logs.php','enabled'=>true],
        ['id'=>'settings','section'=>'SETTINGS','label'=>'Settings','icon'=>'settings','href'=>'settings.php','enabled'=>true],
    ];
}

function saveSidebarItems(array $items): bool {
    $config = readConfig();
    $config['sidebar'] = $items;
    return writeConfig($config);
}

function requireSetup(): void {
    if (!isSetupDone()) {
        $base = '/' . trim(substr(dirname(CONFIG_FILE), strlen($_SERVER['DOCUMENT_ROOT'])), '/') . '/';
        header('Location: ' . $base . 'setup.php');
        exit;
    }
}

function requireLogin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['logged_in'])) {
        $base = '/' . trim(substr(dirname(CONFIG_FILE), strlen($_SERVER['DOCUMENT_ROOT'])), '/') . '/';
        header('Location: ' . $base . 'index.php');
        exit;
    }
}
