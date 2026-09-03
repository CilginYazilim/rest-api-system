<?php
/**
 * =====================================================================
 *  DÜZEN: Sade sayfa
 * ---------------------------------------------------------------------
 *  Hata sayfaları için kullanılır. Menü ve veritabanı gerektirmez;
 *  bağlantı koptuğunda bile bu düzen çalışabilmelidir.
 * =====================================================================
 */

// Hata sayfası veritabanına ulaşamıyor olabilir; current_theme()
// bu durumda sessizce çereze/varsayılana düşer.
$theme = current_theme();
?>
<!DOCTYPE html>
<html lang="tr" data-cy-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?= $theme === 'dark' ? '#0f172a' : '#ffffff' ?>">
    <title><?= e($title ?? 'Hata') ?></title>
    <link rel="icon" type="image/png" href="<?= e(asset('images/logo.png')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/cilginyazilim.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="cy-app">
    <div class="container">
        <?= $content ?? '' ?>
    </div>
</body>
</html>
