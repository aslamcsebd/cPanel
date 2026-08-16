<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
$pageTitle = 'Virtual Hosts — cPanel Manager';
$activePage = 'domains-webvhosts';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Virtual Hosts</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: WebVhosts/list_domains, WebVhosts/list_ssl_capable_domains</p>
  </div>
  <button class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2.5 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 transition">
    <i data-lucide="refresh-cw" class="h-4 w-4"></i> Refresh
  </button>
</div>

<!-- Stats -->
<div class="grid gap-4 sm:grid-cols-3">
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50"><i data-lucide="globe-2" class="h-5 w-5 text-blue-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">Total vHosts</p><p class="text-xl font-semibold text-gray-900 dark:text-white">6</p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-50"><i data-lucide="lock" class="h-5 w-5 text-green-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">SSL Capable</p><p class="text-xl font-semibold text-gray-900 dark:text-white">4</p></div>
  </div>
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-4 shadow-sm flex items-center gap-4">
    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50"><i data-lucide="alert-triangle" class="h-5 w-5 text-amber-600"></i></div>
    <div><p class="text-xs text-gray-500 dark:text-gray-400">No SSL</p><p class="text-xl font-semibold text-gray-900 dark:text-white">2</p></div>
  </div>
</div>

<!-- Virtual Hosts Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Domain</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Document Root</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">SSL Capable</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">PHP Version</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
        $vhosts = [
          ['example.com',       'Primary',   '/public_html',         true,  'PHP 8.2'],
          ['shop.example.com',  'Addon',     '/public_html/shop',    true,  'PHP 8.1'],
          ['blog.example.com',  'Subdomain', '/public_html/blog',    true,  'PHP 8.2'],
          ['api.example.com',   'Subdomain', '/public_html/api',     true,  'PHP 8.2'],
          ['dev.example.com',   'Subdomain', '/public_html/dev',     false, 'PHP 7.4'],
          ['staging.example.com','Subdomain','/public_html/staging', false, 'PHP 8.1'],
        ];
        foreach ($vhosts as [$domain, $type, $root, $ssl, $php]):
          $typeBadge = match($type) {
            'Primary'  => 'bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400',
            'Addon'    => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-900/20 dark:text-indigo-400',
            default    => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
          };
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= $domain ?></td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $typeBadge ?>"><?= $type ?></span></td>
          <td class="px-5 py-3.5 text-sm font-mono text-gray-500 dark:text-gray-400"><?= $root ?></td>
          <td class="px-5 py-3.5">
            <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); if ($ssl): ?>
            <span class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400"><i data-lucide="check-circle" class="h-3.5 w-3.5"></i> Yes</span>
            <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); else: ?>
            <span class="inline-flex items-center gap-1 text-xs text-gray-400"><i data-lucide="x-circle" class="h-3.5 w-3.5"></i> No</span>
            <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); endif; ?>
          </td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $php ?></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <a href="ssl.php" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="SSL"><i data-lucide="lock" class="h-4 w-4"></i></a>
              <a href="dns.php" class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="DNS"><i data-lucide="network" class="h-4 w-4"></i></a>
            </div>
          </td>
        </tr>
        <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); include '../includes/layout_end.php'; ?>
