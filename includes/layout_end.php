  </main>
</div>

<!-- Page loader -->
<div id="page-loader" class="fixed inset-0 z-[9999] flex items-center justify-center bg-white/80 dark:bg-gray-900/80 backdrop-blur-sm pointer-events-none opacity-0 transition-opacity duration-200">
  <div class="flex flex-col items-center gap-3">
    <div class="relative h-10 w-10">
      <div class="absolute inset-0 rounded-full border-[3px] border-gray-200 dark:border-gray-700"></div>
      <div class="absolute inset-0 rounded-full border-[3px] border-transparent border-t-blue-600 animate-spin"></div>
    </div>
    <span class="text-xs font-medium text-gray-500 dark:text-gray-400 tracking-wide">Loading...</span>
  </div>
</div>

<script>
(function () {
  var loader = document.getElementById('page-loader');
  var timer;

  function show() {
    clearTimeout(timer);
    loader.style.pointerEvents = 'all';
    loader.style.opacity = '1';
  }

  function hide() {
    loader.style.opacity = '0';
    loader.style.pointerEvents = 'none';
  }

  // Show on any navigation link click (not buttons, not #anchors, not target=_blank)
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a[href]');
    if (!a) return;
    var href = a.getAttribute('href');
    if (!href || href === '#' || href.startsWith('#') || href.startsWith('javascript')) return;
    if (a.target === '_blank') return;
    if (e.ctrlKey || e.metaKey || e.shiftKey) return;
    show();
    // Safety fallback — hide if navigation stalls
    timer = setTimeout(hide, 8000);
  });

  // Show on form submit
  document.addEventListener('submit', function (e) {
    if (e.target.method && e.target.method.toLowerCase() === 'get') show();
    timer = setTimeout(hide, 8000);
  });

  // Hide when page becomes visible again (back/forward)
  window.addEventListener('pageshow', hide);
  window.addEventListener('popstate', hide);
})();
</script>

<script>var APP_BASE = '<?= rtrim(str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(dirname(__FILE__))), '/') ?>';</script>
<script src="<?= $basePath ?>assets/js/app.js"></script>
<script>lucide.createIcons();</script>
</body>
</html>
