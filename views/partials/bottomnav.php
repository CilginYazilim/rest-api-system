<?php
/**
 * =====================================================================
 *  PARÇA: Mobil alt gezinme çubuğu
 * ---------------------------------------------------------------------
 *  NEDEN AYRI BİR MOBİL MENÜ?
 *  Telefonda sol menü bir "çekmece"dir: görmek için önce üst köşedeki
 *  düğmeye basmak gerekir. Gün içinde en çok kullanılan sayfalara her
 *  seferinde iki dokunuşla gitmek yorucudur.
 *
 *  Alt çubuk bu maliyeti tek dokunuşa indirir ve düğmeleri BAŞPARMAK
 *  BÖLGESİNE (ekranın alt üçte biri) yerleştirir — tek elle telefon
 *  kullanan birinin rahatça uzanabildiği tek alan burasıdır.
 *
 *  Öğeler config/menu.php'deki TÜM bağlantıların ilk üçüdür; dördüncü
 *  düğme sol çekmeceyi açar. Beşten fazla sütun, dokunma hedeflerini
 *  44px'in altına düşürür.
 *
 *  Yalnızca <768px'te görünür (bkz. admin.css → "MOBİL ALT ÇUBUK").
 * =====================================================================
 */

use App\Core\Config;

/* Grupları düzleştirip ilk üç bağlantıyı alıyoruz. */
$flat = [];

foreach (Config::get('menu', []) as $group) {
    foreach ($group['items'] as $item) {
        $flat[] = $item;
    }
}

$items = array_slice($flat, 0, 3);
?>
<nav class="cy-bottomnav" id="cy_bottomnav" aria-label="Hızlı gezinme">
    <?php foreach ($items as $item): ?>
        <a class="cy-bottomnav__item<?= is_route($item['route']) ? ' is-active' : '' ?>"
           href="<?= e(url($item['route'])) ?>"
           <?= is_route($item['route']) ? 'aria-current="page"' : '' ?>>
            <?= icon($item['icon'], 'cy-icon cy-bottomnav__icon') ?>
            <span><?= e($item['short'] ?? $item['label']) ?></span>
        </a>
    <?php endforeach; ?>

    <!-- "Menü": sol çekmeceyi açar; alt çubuğa sığmayan sayfalar
         buradan açılır. -->
    <button type="button" class="cy-bottomnav__item" id="cy_bottomnav_more"
            aria-controls="cy_sidebar" aria-expanded="false">
        <?= icon('menu', 'cy-icon cy-bottomnav__icon') ?>
        <span>Menü</span>
    </button>
</nav>
