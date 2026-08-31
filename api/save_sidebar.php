<?php
require_once '../includes/auth.php';
requireLogin();

$collapsed = ($_POST['collapsed'] ?? 'false') === 'true';
$config = readConfig();
$config['app']['sidebar_collapsed'] = $collapsed;
echo json_encode(['ok' => writeConfig($config)]);
