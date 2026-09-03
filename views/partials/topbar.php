<?php
/**
 * =====================================================================
 *  PARÇA: Üst çubuk
 * ---------------------------------------------------------------------
 *  Soldan sağa: menü düğmesi · sayfa başlığı · tema düğmesi · hesap menüsü
 *
 *  Menü düğmesi iki farklı iş yapar:
 *    - Geniş ekranda : menüyü daraltır/genişletir (ikon moduna alır)
 *    - Dar ekranda   : menüyü çekmece olarak açar/kapatır
 *  Ayrımı app.js ekran genişliğine bakarak yapar.
 * =====================================================================
 */

/** @var App\Models\User|null $currentUser */
$user = $currentUser ?? null;
?>
<header class="cy-topbar">

    <button type="button"
            class="cy-topbar__toggle"
            id="cy_sidebar_toggle"
            aria-label="Menüyü aç/kapat"
            aria-controls="cy_sidebar"
            aria-expanded="false">
        <?= icon('menu') ?>
    </button>

    <div class="cy-topbar__heading">
        <h1><?= e($pageTitle ?? 'Panel') ?></h1>
        <?php if (!empty($pageSubtitle)): ?>
            <p><?= e($pageSubtitle) ?></p>
        <?php endif; ?>
    </div>

    <div class="cy-topbar__actions">

        <!--
            Tema değiştirici.

            data-persist="1" : Giriş yapılmışsa tercih HESABA kaydedilir
            (api/preferences/theme). Böylece kullanıcı başka bir
            bilgisayardan girdiğinde de kendi temasını bulur.
            Giriş ekranında bu bayrak yoktur; orada yalnızca çerez
            kullanılır, çünkü kaydedilecek bir hesap henüz yoktur.
        -->
        <button type="button" class="cy-topbar__toggle" id="cy_theme_toggle"
                <?= $user !== null ? 'data-persist="1"' : '' ?>
                aria-label="Açık/koyu tema" title="Açık/koyu tema">
            <span class="cy-theme-icon cy-theme-icon--light"><?= icon('moon') ?></span>
            <span class="cy-theme-icon cy-theme-icon--dark d-none"><?= icon('sun') ?></span>
        </button>

        <?php if ($user !== null): ?>
            <div class="dropdown">
                <button class="cy-usermenu" type="button" data-bs-toggle="dropdown"
                        data-bs-display="static" aria-expanded="false">
                    <span class="cy-avatar cy-avatar--sm cy-avatar--initial"><?= e($user->initials()) ?></span>
                    <span class="cy-usermenu__name"><?= e($user->name) ?></span>
                </button>

                <div class="dropdown-menu dropdown-menu-end cy-dropdown">
                    <div class="cy-dropdown__header">
                        <strong><?= e($user->fullName()) ?></strong>
                        <span><?= e($user->email) ?></span>
                    </div>

                    <hr class="cy-divider my-1">

                    <!--
                        ÇIKIŞ NEDEN FORM (POST)?
                        Bağlantı (GET) olsaydı, kötü niyetli bir sitedeki
                        <img src="...logout"> etiketi oturumunuzu kapatabilirdi.
                        POST + CSRF anahtarı bunu engeller.
                    -->
                    <form method="post" action="<?= e(url('logout')) ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item dropdown-item--danger w-100">
                            <?= icon('logout', 'cy-icon cy-icon--sm') ?> Çıkış Yap
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</header>
