<?php
// Flash message partial — include inside page body where needed
$flash = getFlash();
if ($flash): ?>
<div id="flash-msg" class="flex items-center gap-3 px-4 py-3 rounded-lg mb-5 text-sm font-medium
  <?= $flash['type'] === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : ($flash['type'] === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-yellow-50 text-yellow-800 border border-yellow-200') ?> fade-in">
  <i class="fa <?= $flash['type'] === 'success' ? 'fa-check-circle text-green-500' : ($flash['type'] === 'error' ? 'fa-exclamation-circle text-red-500' : 'fa-triangle-exclamation text-yellow-500') ?>"></i>
  <?= htmlspecialchars($flash['message']) ?>
  <button onclick="document.getElementById('flash-msg').remove()" class="ml-auto text-current opacity-60 hover:opacity-100"><i class="fa fa-xmark"></i></button>
</div>
<?php endif; ?>
