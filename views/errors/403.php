<?php /* GÖRÜNÜM: 403 – Yetkisiz erişim */ ?>
<div class="cy-error">
    <div>
        <div class="cy-error__code">403</div>
        <h1 class="cy-error__title">Bu sayfaya erişim yetkiniz yok</h1>
        <p class="cy-error__text">
            Hesabınızın rolü bu bölümü görüntülemeye izin vermiyor.
            Erişim gerekiyorsa bir yöneticiyle iletişime geçin.
        </p>
        <a class="btn cy-btn cy-btn--primary" href="<?= e(url('dashboard')) ?>">
            <?= icon('dashboard', 'cy-icon cy-icon--sm') ?> Panele Dön
        </a>
    </div>
</div>
