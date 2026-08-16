<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
$pageTitle = 'DMARC — cPanel Manager';
$activePage = 'email-dmarc';
include '../includes/layout.php';
?>

<div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
  <div>
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">DMARC</h1>
    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">UAPI: EmailAuth/apply_dmarc, EmailAuth/remove_dmarc</p>
  </div>
  <button onclick="document.getElementById('modal-add-dmarc').classList.remove('hidden')" class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-blue-700 transition">
    <i data-lucide="plus" class="h-4 w-4"></i> Add DMARC Policy
  </button>
</div>

<!-- Info Banner -->
<div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-900/20 p-4 flex items-start gap-3">
  <i data-lucide="info" class="h-5 w-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5"></i>
  <p class="text-sm text-blue-700 dark:text-blue-400">DMARC (Domain-based Message Authentication, Reporting & Conformance) tells receiving mail servers what to do with emails that fail SPF or DKIM checks. Requires SPF and DKIM to be configured first.</p>
</div>

<!-- DMARC Table -->
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden">
  <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-700">
      <thead class="bg-gray-50 dark:bg-gray-700/50">
        <tr>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Domain</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Policy</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Subdomain Policy</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Report Email</th>
          <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">% Checked</th>
          <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
        <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin();
        $records = [
          ['example.com', 'none', 'none', 'dmarc@example.com', '100'],
          ['shop.example.com', 'quarantine', 'reject', 'dmarc@example.com', '100'],
          ['dev.example.com', '—', '—', '—', '—'],
        ];
        $policyColors = [
          'none'       => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
          'quarantine' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/20 dark:text-amber-400',
          'reject'     => 'bg-red-50 text-red-700 dark:bg-red-900/20 dark:text-red-400',
          '—'          => 'bg-gray-100 text-gray-400 dark:bg-gray-700 dark:text-gray-500',
        ];
        foreach ($records as [$domain, $policy, $spolicy, $rua, $pct]):
        ?>
        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
          <td class="px-5 py-3.5 text-sm font-medium text-gray-900 dark:text-gray-100"><?= $domain ?></td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $policyColors[$policy] ?? $policyColors['—'] ?>"><?= $policy ?></span></td>
          <td class="px-5 py-3.5"><span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium <?= $policyColors[$spolicy] ?? $policyColors['—'] ?>"><?= $spolicy ?></span></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $rua ?></td>
          <td class="px-5 py-3.5 text-sm text-gray-500 dark:text-gray-400"><?= $pct !== '—' ? $pct.'%' : '—' ?></td>
          <td class="px-5 py-3.5 text-right">
            <div class="flex items-center justify-end gap-1">
              <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); if ($policy !== '—'): ?>
              <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-gray-500 hover:bg-gray-100" title="Edit"><i data-lucide="pencil" class="h-4 w-4"></i></button>
              <button class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-red-400 hover:bg-red-50" title="Remove"><i data-lucide="trash-2" class="h-4 w-4"></i></button>
              <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); else: ?>
              <button onclick="document.getElementById('modal-add-dmarc').classList.remove('hidden')" class="inline-flex items-center gap-1.5 h-8 rounded-lg bg-blue-600 px-3 text-xs font-medium text-white hover:bg-blue-700">Add Policy</button>
              <?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); endif; ?>
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

<!-- Add DMARC Modal -->
<div id="modal-add-dmarc" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
  <div class="w-full max-w-lg rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-xl">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-gray-700">
      <h3 class="text-base font-semibold text-gray-900 dark:text-white">Add DMARC Policy</h3>
      <button onclick="document.getElementById('modal-add-dmarc').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i data-lucide="x" class="h-5 w-5"></i></button>
    </div>
    <div class="p-6 space-y-4">
      <div>
        <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Domain</label>
        <select class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
          <option>example.com</option><option>shop.example.com</option><option>dev.example.com</option>
        </select>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Policy (p=)</label>
          <select class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
            <option value="none">none — Monitor only</option>
            <option value="quarantine">quarantine — Send to spam</option>
            <option value="reject">reject — Reject message</option>
          </select>
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Subdomain Policy (sp=)</label>
          <select class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400">
            <option value="none">none</option>
            <option value="quarantine">quarantine</option>
            <option value="reject">reject</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Report Email (rua=)</label>
          <input type="email" placeholder="dmarc@example.com" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">% of messages (pct=)</label>
          <input type="number" value="100" min="1" max="100" class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm outline-none focus:border-blue-400" />
        </div>
      </div>
    </div>
    <div class="flex justify-end gap-2 px-6 py-4 border-t border-gray-100 dark:border-gray-700">
      <button onclick="document.getElementById('modal-add-dmarc').classList.add('hidden')" class="rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">Cancel</button>
      <button class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Apply Policy</button>
    </div>
  </div>
</div>

<?php
require_once '../includes/auth.php';
requireSetup();
requireLogin(); include '../includes/layout_end.php'; ?>
