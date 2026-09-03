<?php
/**
 * =====================================================================
 *  DÜZEN: Yönetim Paneli
 * ---------------------------------------------------------------------
 *  Tüm panel sayfalarının ortak çerçevesi: <head>, sol menü, üst çubuk
 *  ve içerik alanı. Sayfaya özel HTML, $content değişkeninde gelir.
 *
 *  TEMA VE MENÜ DURUMU NEDEN ÇEREZDE?
 *  Bu iki tercih sunucuda okunup daha ilk HTML'de uygulanıyor. Eğer
 *  JavaScript ile uygulasaydık, sayfa bir an açık temada görünüp sonra
 *  koyuya atlardı ("flash of wrong theme"). Ayrıca Content-Security-Policy
 *  satır içi script'e izin vermiyor; çerez yöntemi bu kısıtla da uyumlu.
 *
 *  @var string      $content Sayfa içeriği (View sınıfı doldurur)
 *  @var string      $title   Sayfa başlığı
 *  @var string|null $subtitle
 * =====================================================================
 */

use App\Core\Csrf;
use App\Core\Flash;
use App\Core\View;

/* Tema HESABA bağlıdır (bkz. current_theme). Giriş yapan kullanıcı
 * hangi cihazdan gelirse gelsin kendi tercihini görür.
 * Menünün daraltılmış olup olmaması ise cihaza özgü bir tercihtir;
 * ekran boyutuyla ilgili olduğu için çerezde kalması daha doğrudur. */
$theme     = current_theme();
$collapsed = ($_COOKIE['cy_sidebar'] ?? '') === 'collapsed';

$pageTitle    = $title ?? 'Panel';
$pageSubtitle = $subtitle ?? null;
$flashes      = Flash::pull();
?>
<!DOCTYPE html>
<html lang="tr" data-cy-theme="<?= e($theme) ?>">
<head>
    <meta charset="UTF-8">
    <!-- viewport-fit=cover : iPhone çentiğinin altındaki alanı da kullanabilmek için -->
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="author" content="Çılgın Yazılım - cilginyazilim.com">
    <meta name="robots" content="noindex, nofollow">

    <!--
        theme-color: Android Chrome ve iOS Safari, tarayıcının kendi
        çubuğunu (adres çubuğu / durum çubuğu) bu renge boyar. Vermezsek
        koyu temada sayfa siyah, tarayıcı çubuğu bembeyaz kalır ve panel
        "yarım" görünür. Değeri temaya göre üretiyoruz; app.js tema
        değişiminde bunu anında günceller.
    -->
    <meta name="theme-color" content="<?= $theme === 'dark' ? '#0f172a' : '#ffffff' ?>">

    <!-- iOS'ta ana ekrana eklendiğinde tam ekran açılsın -->
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">

    <!-- CSRF anahtarı: JavaScript bunu okuyup her AJAX isteğine ekler. -->
    <meta name="csrf-token" content="<?= e(Csrf::token()) ?>">
    <!-- JavaScript'in adres üretebilmesi için taban adres kalıbı -->
    <meta name="cy-base" content="<?= e(url('__PATH__')) ?>">

    <title><?= e($pageTitle) ?> · <?= e($appName ?? 'CY REST API') ?></title>

    <link rel="icon" type="image/png" href="<?= e(asset('images/logo.png')) ?>">

    <!-- Yükleme sırası önemlidir; sonraki dosya öncekini ezebilir. -->
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

<body class="cy-app<?= $collapsed ? ' is-collapsed' : '' ?>">

    <!-- Klavye kullanıcıları menüyü atlayıp doğrudan içeriğe geçebilsin -->
    <a href="#cy-content" class="cy-sr-only">İçeriğe geç</a>

    <div class="cy-shell">

        <?php View::partial('partials/sidebar'); ?>

        <!-- Mobilde menü açıkken arka planı karartan katman -->
        <div class="cy-backdrop" id="cy_backdrop" hidden></div>

        <div class="cy-main">

            <?php View::partial('partials/topbar', [
                'pageTitle'    => $pageTitle,
                'pageSubtitle' => $pageSubtitle,
            ]); ?>

            <main class="cy-content" id="cy-content">
                <?= $content ?? '' ?>
            </main>

            <?php // Telefonda başparmak bölgesindeki hızlı gezinme çubuğu ?>
            <?php View::partial('partials/bottomnav'); ?>

            <footer class="cy-footer">
                <?= date('Y') ?> ·
                <a href="https://cilginyazilim.com" target="_blank" rel="noopener">cilginyazilim.com</a>
                ·
                <a href="https://github.com/CilginYazilim/rest-api-system"
                   target="_blank" rel="noopener">GitHub</a>
                ·
                <a href="https://cilginyazilim.com/kutuphane"
                   target="_blank" rel="noopener">Örnek Kod Kütüphanesi</a>
                · PHP <?= e(PHP_VERSION) ?>
            </footer>
        </div>
    </div>

    <!-- Bildirim balonlarının ekleneceği kapsayıcı -->
    <div class="toast-container cy-toast-container position-fixed top-0 end-0 p-3" id="cy_toasts"></div>

    <!--
        Sunucu tarafındaki flash mesajlarını JavaScript'e taşıyoruz.
        type="application/json" olduğu için tarayıcı bunu çalıştırmaz;
        sadece veri taşır. Böylece satır içi script'e gerek kalmaz
        (Content-Security-Policy uyumu).
    -->
    <script type="application/json" id="cy_flash"><?= json_encode($flashes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <!--
        JAVASCRIPT YÜKLEME SIRASI:
        1) jQuery           → kodumuz buna bağımlı
        2) bootstrap.bundle → Modal, Toast, Dropdown (Popper dahil)
        3) app.js           → panel kabuğu (menü, tema, bildirim)
        Sıra bozulursa "$ is not defined" gibi hatalar alırsınız.
    -->
    <script src="<?= e(asset('js/jquery-3.7.0.js')) ?>"></script>
    <script src="<?= e(asset('js/bootstrap.bundle.js')) ?>"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>

    <?php // Sayfaya özel script dosyaları (görünümler $scripts ile bildirir) ?>
    <?php foreach (($scripts ?? []) as $script): ?>
        <script src="<?= e(asset('js/' . $script)) ?>"></script>
    <?php endforeach; ?>
    <?php // Projeye özel </body> ekleri (isteğe bağlı) ?>
    <?php if (is_file(CY_BASE . '/views/partials/body_extra.php')): ?>
        <?php View::partial('partials/body_extra'); ?>
    <?php endif; ?>
</body>
</html>
