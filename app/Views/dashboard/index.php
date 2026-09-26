<?= $this->extend('layouts/app') ?>
<?= $this->section('content') ?>
<?= $this->include('partials/login') ?>
<div class="app-shell">
  <?= $this->include('partials/sidebar') ?>
  <main class="main-content">
    <?= $this->include('partials/topbar') ?>
    <div id="pageContent"></div>
  </main>
</div>
<div class="toast-wrap" id="toastWrap"></div>
<div class="modal-backdrop" id="modalBackdrop"><div class="modal" id="modalContent"></div></div>
<?= $this->endSection() ?>