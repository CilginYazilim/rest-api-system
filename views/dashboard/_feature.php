<?php
/**
 * =====================================================================
 *  PARÇA: Kontrol panelinde API özeti
 * ---------------------------------------------------------------------
 *  views/dashboard/index.php bu dosyayı VARSA basar; ortak panel
 *  düzeni altı örnek projede aynı kalır.
 * =====================================================================
 */

use App\Core\Router;
use App\Core\Session;

$base = (Session::isHttps() ? 'https://' : 'http://')
      . (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')
      . Router::basePath()
      . '/api/v1';
?>
<div class="cy-card mt-3">
    <div class="cy-card__head">
        <h2 class="cy-section-title">
            <?= icon('activity', 'cy-icon cy-icon--sm') ?> API'yi Denemek
        </h2>
    </div>

    <div class="cy-card__body">
        <p class="mb-2">
            Önce <a href="<?= e(url('tokens')) ?>">API Jetonları</a> sayfasından bir jeton üretin,
            sonra aşağıdaki komutu çalıştırın:
        </p>

        <code class="cy-code">curl -H "Authorization: Bearer &lt;jetonunuz&gt;" \
     "<?= e($base) ?>/users?per=10&amp;page=2"</code>

        <p class="cy-muted mt-3 mb-0" style="font-size:.8125rem">
            Yanıtın <code>meta</code> bölümünde sayfalama bilgisi,
            <code>links</code> bölümünde bir sonraki sayfanın adresi gelir.
            Tüm uç noktalar ve hata kodları için
            <a href="<?= e(url('docs')) ?>">API Belgeleri</a> sayfasına bakın.
        </p>
    </div>
</div>
