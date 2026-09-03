<?php
/**
 * GÖRÜNÜM: 404 – Sayfa bulunamadı
 *
 * GÜVENLİK NOTU: İstenen adresi ekrana basarken e() ile kaçışlıyoruz.
 * Aksi halde saldırgan adres çubuğuna script yazıp kurbanına
 * gönderebilirdi (yansıyan XSS – reflected XSS).
 */
?>
<div class="cy-error">
    <div>
        <div class="cy-error__code">404</div>
        <h1 class="cy-error__title">Sayfa bulunamadı</h1>
        <p class="cy-error__text">
            Aradığınız sayfa taşınmış veya hiç var olmamış olabilir.
            <?php if (!empty($path)): ?>
                <br><code class="cy-mono"><?= e($path) ?></code>
            <?php endif; ?>
        </p>
        <a class="btn cy-btn cy-btn--primary" href="<?= e(url('dashboard')) ?>">
            <?= icon('dashboard', 'cy-icon cy-icon--sm') ?> Panele Dön
        </a>
    </div>
</div>
