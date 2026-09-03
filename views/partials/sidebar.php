<?php
/**
 * =====================================================================
 *  PARÇA: Sol menü
 * ---------------------------------------------------------------------
 *  Menü öğeleri config/menu.php dosyasından okunur. Yeni bir sayfa
 *  eklerken buraya değil, O DOSYAYA satır eklersiniz — menünün
 *  görünümü ile içeriği ayrı durur.
 *
 *  DİKKAT: Menüden bir bağlantıyı çıkarmak bir GÜVENLİK önlemi
 *  DEĞİLDİR; sadece arayüz düzenidir. Asıl koruma routes/web.php
 *  içindeki ara katmanlardadır ('auth' gibi).
 * =====================================================================
 */

use App\Core\Config;

/** @var App\Models\User|null $currentUser */
$user = $currentUser ?? null;

/** @var array<int,array{label:string,items:array<int,array{route:string,icon:string,label:string}>}> $menu */
$menu = Config::get('menu', []);
?>
<aside class="cy-sidebar" id="cy_sidebar" aria-label="Ana gezinme">

    <!-- Marka -->
    <a class="cy-sidebar__brand" href="<?= e(url('dashboard')) ?>">
        <span class="cy-sidebar__logo">
            <img src="<?= e(asset('images/logo.png')) ?>" alt="">
        </span>
        <span class="cy-sidebar__title">
            <strong><?= e($appName ?? '') ?></strong>
            <span><?= e($appBrand ?? 'Çılgın Yazılım') ?></span>
        </span>
    </a>

    <!-- Bağlantılar -->
    <nav class="cy-sidebar__nav">
        <?php foreach ($menu as $group): ?>
            <div class="cy-nav-group">
                <span class="cy-nav-group__label"><?= e($group['label']) ?></span>

                <?php foreach ($group['items'] as $item): ?>
                    <a class="cy-nav-link<?= is_route($item['route']) ? ' is-active' : '' ?>"
                       href="<?= e(url($item['route'])) ?>"
                       data-title="<?= e($item['label']) ?>"
                       <?= is_route($item['route']) ? 'aria-current="page"' : '' ?>>
                        <?= icon($item['icon']) ?>
                        <span class="cy-nav-link__text"><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </nav>

    <!-- Giriş yapan kullanıcı -->
    <?php if ($user !== null): ?>
        <div class="cy-sidebar__footer">
            <div class="cy-side-user">
                <span class="cy-avatar cy-avatar--sm cy-avatar--initial"><?= e($user->initials()) ?></span>

                <span class="cy-side-user__info">
                    <span class="cy-side-user__name"><?= e($user->fullName()) ?></span>
                    <span class="cy-side-user__role"><?= e($user->email) ?></span>
                </span>
            </div>
        </div>
    <?php endif; ?>
</aside>
