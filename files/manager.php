<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
$pageTitle = 'File Manager — cPanel Manager';
$activePage = 'files';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">File Manager</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: Fileman/list_files, Fileman/get_file_content</p>
  </div>
  <div class="flex gap-2">
    <button class="inline-flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50">
      <i data-lucide="folder-plus" class="h-4 w-4"></i> New Folder
    </button>
    <button class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
      <i data-lucide="upload" class="h-4 w-4"></i> Upload
    </button>
  </div>
</div>

<!-- Breadcrumb -->
<div class="flex items-center gap-1 text-sm">
  <button class="text-blue-600 hover:underline">Home</button>
  <i data-lucide="chevron-right" class="h-4 w-4 text-gray-400"></i>
  <button class="text-blue-600 hover:underline">public_html</button>
  <i data-lucide="chevron-right" class="h-4 w-4 text-gray-400"></i>
  <span class="text-gray-500 dark:text-gray-400">wp-content</span>
</div>

<div class="grid gap-4 lg:grid-cols-4">
  <!-- Tree -->
  <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm p-3">
    <p class="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500 px-2 pb-2">Directories</p>
    <div class="space-y-0.5 text-sm">
      <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
      $tree = [
        ['public_html', true, [
          ['wp-content', true, []],
          ['wp-admin', false, []],
          ['wp-includes', false, []],
        ]],
        ['mail', false, []],
        ['logs', false, []],
        ['.trash', false, []],
      ];
      function renderTree($items, $depth = 0) {
        foreach ($items as [$name, $open, $children]) {
          $pad = $depth * 12;
          $active = $name === 'wp-content' ? 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-700';
          echo "<button class=\"w-full flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm {$active}\" style=\"padding-left:" . ($pad + 8) . "px\">";
          echo "<i data-lucide=\"" . ($open ? 'folder-open' : 'folder') . "\" class=\"h-4 w-4 flex-shrink-0\"></i>{$name}</button>";
          if ($children) renderTree($children, $depth + 1);
        }
      }
      renderTree($tree);
      ?>
    </div>
  </div>

  <!-- File List -->
  <div class="lg:col-span-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
    <!-- Toolbar -->
    <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100 dark:border-gray-700 flex-wrap">
      <div class="relative flex-1 min-w-[180px]">
        <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400"></i>
        <input type="text" placeholder="Search files..." class="h-9 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-gray-50 dark:bg-gray-700 pl-9 pr-3 text-sm outline-none focus:border-blue-400" />
      </div>
      <button class="inline-flex items-center gap-1.5 h-9 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50"><i data-lucide="copy" class="h-4 w-4"></i> Copy</button>
      <button class="inline-flex items-center gap-1.5 h-9 rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm text-gray-600 dark:text-gray-300 hover:bg-gray-50"><i data-lucide="scissors" class="h-4 w-4"></i> Move</button>
      <button class="inline-flex items-center gap-1.5 h-9 rounded-lg border border-red-200 bg-red-50 px-3 text-sm text-red-600 hover:bg-red-100"><i data-lucide="trash-2" class="h-4 w-4"></i> Delete</button>
    </div>
    <div class="overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700/50">
          <tr>
            <th class="px-4 py-3 w-8"><input type="checkbox" class="h-4 w-4 rounded border-gray-300" /></th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Size</th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Modified</th>
            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Permissions</th>
            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
          <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
          $files = [
            ['themes','folder','—','2025-07-10','drwxr-xr-x'],
            ['plugins','folder','—','2025-07-12','drwxr-xr-x'],
            ['uploads','folder','—','2025-07-15','drwxrwxr-x'],
            ['index.php','php','0 KB','2025-01-01','-rw-r--r--'],
            ['style.css','css','48 KB','2025-06-20','-rw-r--r--'],
            ['functions.php','php','12 KB','2025-07-01','-rw-r--r--'],
            ['screenshot.png','image','156 KB','2025-05-15','-rw-r--r--'],
            ['.htaccess','config','1 KB','2025-07-10','-rw-r--r--'],
          ];
          $icons = ['folder'=>['folder','text-amber-500'],'php'=>['file-code','text-blue-500'],'css'=>['file-code','text-purple-500'],'image'=>['image','text-green-500'],'config'=>['file-cog','text-gray-500']];
          foreach ($files as [$name, $type, $size, $modified, $perms]):
            [$icon, $iconColor] = $icons[$type] ?? ['file','text-gray-400'];
          ?>
          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
            <td class="px-4 py-3"><input type="checkbox" class="h-4 w-4 rounded border-gray-300" /></td>
            <td class="px-4 py-3">
              <div class="flex items-center gap-2">
                <i data-lucide="<?= $icon ?>" class="h-4 w-4 <?= $iconColor ?> flex-shrink-0"></i>
                <span class="text-sm font-medium text-gray-900 dark:text-gray-100"><?= $name ?></span>
              </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"><?= $size ?></td>
            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400"><?= $modified ?></td>
            <td class="px-4 py-3 text-xs font-mono text-gray-500 dark:text-gray-400"><?= $perms ?></td>
            <td class="px-4 py-3 text-right">
              <div class="flex items-center justify-end gap-1">
                <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); if ($type !== 'folder'): ?>
                <button class="inline-flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100" title="Edit"><i data-lucide="pencil" class="h-3.5 w-3.5"></i></button>
                <button class="inline-flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100" title="Download"><i data-lucide="download" class="h-3.5 w-3.5"></i></button>
                <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); endif; ?>
                <button class="inline-flex h-7 w-7 items-center justify-center rounded text-gray-500 hover:bg-gray-100" title="Rename"><i data-lucide="type" class="h-3.5 w-3.5"></i></button>
                <button class="inline-flex h-7 w-7 items-center justify-center rounded text-red-400 hover:bg-red-50" title="Delete"><i data-lucide="trash-2" class="h-3.5 w-3.5"></i></button>
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
</div>

<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); include '../includes/layout_end.php'; ?>
