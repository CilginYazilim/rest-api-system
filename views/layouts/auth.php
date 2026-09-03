<?php
/**
 * =====================================================================
 *  DÜZEN: Giriş ekranı
 * ---------------------------------------------------------------------
 *  Panel düzeninden ayrıdır: sol menü ve üst çubuk yoktur, sayfanın
 *  tamamını iki sütunlu bir tanıtım + form yerleşimi kaplar.
 *  Mobilde tanıtım sütunu gizlenir, form tam ekran olur.
 * =====================================================================
 */

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;

// Giriş ekranında henüz kim olduğunu bilmiyoruz; çerezdeki son
// tercih kullanılır, o da yoksa açık tema (bkz. current_theme).
$theme    = current_theme();
$flashes  = Flash::pull();
?>
<!DOCTYPE html>
<html lang="tr" data-cy-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= $theme === 'dark' ? '#0f172a' : '#ffffff' ?>">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">

    <title><?= e($title ?? 'Giriş') ?> · <?= e($appName ?? 'CY REST API') ?></title>

    <link rel="icon" type="image/png" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/cilginyazilim.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
    <?php
    /* PROJEYE ÖZEL <head> EKLERİ
     * -------------------------------------------------------------
     * Bu iskelet altı ayrı örnek projede paylaşılıyor. Her projenin
     * <head> içine kendi satırlarını (manifest, dil etiketleri…)
     * eklemesi gerekebilir. Düzen dosyasını her projede kopyalayıp
     * değiştirmek yerine, İSTEĞE BAĞLI bir parça bırakıyoruz:
     * dosya varsa basılır, yoksa hiçbir şey olmaz. */
    if (is_file(CY_BASE . '/views/partials/head_extra.php')) {
        View::partial('partials/head_extra');
    }
    ?>
</head>
<body class="cy-app">

    <?= $content ?? '' ?>

    <div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="cy_toasts"></div>

    <script type="application/json" id="cy_flash"><?= json_encode($flashes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <script src="<?= e(asset('js/jquery-3.7.0.js')) ?>"></script>
    <script src="<?= e(asset('js/bootstrap.bundle.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
    <script src="<?= e(asset('js/login.js')) ?>"></script>
    <?php // Projeye özel </body> ekleri (isteğe bağlı) ?>
    <?php if (is_file(CY_BASE . '/views/partials/body_extra.php')): ?>
        <?php View::partial('partials/body_extra'); ?>
    <?php endif; ?>
</body>
</html>
