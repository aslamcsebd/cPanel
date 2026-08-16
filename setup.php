<?php
require_once 'includes/config.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Already set up — go to login
if (isSetupDone()) {
    header('Location: index.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm']  ?? '';

    if (!$username || !$password) {
        $error = 'Username and password are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $written = writeConfig([
            'app' => [
                'username' => $username,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ],
            'cpanel' => (object)[],
        ]);
        if (!$written) {
            $error = 'Cannot write config.json — check folder permissions: chmod 777 ' . dirname(CONFIG_FILE);
        } else {
            $_SESSION['logged_in'] = true;
            $_SESSION['app_user']  = $username;
            header('Location: settings.php?setup=1'); exit;
        }
    }
}
?>
<!doctype html>
<html lang="en" class="">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Setup — cPanel Manager</title>
  <script>(function(){var t=<?= json_encode(readConfig()['app']['theme'] ?? 'light') ?>;if(t==='dark')document.documentElement.classList.add('dark')})()</script>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={darkMode:"class"}</script>
  <style>
    .dark input { color-scheme: dark; }
  </style>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-gray-50 dark:bg-gray-900 font-sans flex items-center justify-center p-4">
  <div class="w-full max-w-sm">
    <div class="mb-8 flex flex-col items-center">
      <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-600 mb-4">
        <i data-lucide="server" class="h-6 w-6 text-white"></i>
      </div>
      <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">First Time Setup</h1>
      <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Create your admin account to get started</p>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6 shadow-sm">
      <?php if ($error): ?>
      <div class="mb-4 rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-700 dark:text-red-400">
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>
      <form method="POST" class="space-y-4">
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
          <input type="text" name="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" placeholder="admin" required class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm text-gray-900 dark:text-gray-100 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
          <input type="password" name="password" placeholder="Min 6 characters" required class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm text-gray-900 dark:text-gray-100 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
        </div>
        <div>
          <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm Password</label>
          <input type="password" name="confirm" placeholder="Repeat password" required class="h-10 w-full rounded-lg border border-gray-200 dark:border-gray-600 bg-white dark:bg-gray-700 px-3 text-sm text-gray-900 dark:text-gray-100 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100" />
        </div>
        <button type="submit" class="block w-full rounded-lg bg-blue-600 px-4 py-2.5 text-center text-sm font-medium text-white hover:bg-blue-700 transition">
          Create Account & Continue
        </button>
      </form>
    </div>
    <p class="mt-4 text-center text-xs text-gray-400 dark:text-gray-500">Credentials stored locally — no database required</p>
  </div>
<script>lucide.createIcons();</script>
</body>
</html>
