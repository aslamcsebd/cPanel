<?php
require_once '../includes/auth.php';
requireLogin();

$theme  = $_POST['theme'] ?? 'light';
$theme  = $theme === 'dark' ? 'dark' : 'light';
$config = readConfig();
$config['app']['theme'] = $theme;
echo json_encode(['ok' => writeConfig($config)]);
