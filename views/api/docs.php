<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: API belgeleri
 * ---------------------------------------------------------------------
 *  Uç noktalar bir DİZİDEN basılır (bkz. ApiDocController). Yeni bir
 *  uç nokta eklendiğinde bu dosyaya dokunmak gerekmez.
 *
 *  @var string                          $baseUrl
 *  @var array<int,array<string,mixed>>  $endpoints
 * =====================================================================
 */

use App\Core\View;
?>

<!-- ==============================================================
     1) BAŞLANGIÇ
============================================================== -->
<div class="cy-card">
    <div class="cy-card__head">
        <h2 class="cy-section-title">
            <?= icon('activity', 'cy-icon cy-icon--sm') ?> Başlarken
        </h2>
    </div>

    <div class="cy-card__body">
        <p class="mb-2">Tüm uç noktalar şu adresin altındadır:</p>
        <code class="cy-code"><?= e($baseUrl) ?></code>

        <h3 class="cy-section-title mt-4 mb-2" style="font-size:.95rem">Kimlik doğrulama</h3>
        <p class="mb-2">
            Her istekte <strong>Bearer jetonu</strong> gönderilir. Jetonu
            <a href="<?= e(url('tokens')) ?>">API Jetonları</a> sayfasından üretebilirsiniz.
        </p>

        <code class="cy-code">curl -H "Authorization: Bearer cy_xxxxx" \
     "<?= e($baseUrl) ?>/users?per=10"</code>

        <p class="cy-muted mt-2 mb-0" style="font-size:.8125rem">
            Jeton <strong>başlıkta</strong> taşınır, adres çubuğunda değil:
            adresteki bir jeton sunucu günlüklerine, tarayıcı geçmişine ve
            <code>Referer</code> başlığına sızar.
        </p>

        <h3 class="cy-section-title mt-4 mb-2" style="font-size:.95rem">Yanıt biçimi</h3>
        <p class="mb-2">Başarılı yanıtlar <code>data</code>, hatalı yanıtlar <code>error</code> anahtarını taşır:</p>

        <code class="cy-code">{ "data": { … }, "meta": { … } }

{ "error": { "code": "validation_failed",
             "message": "Gönderilen veriler geçersiz.",
             "details": { "email": "Geçerli bir e-posta girin." } } }</code>

        <p class="cy-muted mt-2 mb-0" style="font-size:.8125rem">
            <code>code</code> makine, <code>message</code> insan içindir.
            Koşullarınızı <code>code</code> üzerine kurun; mesaj metni değişse
            bile entegrasyonunuz bozulmaz.
        </p>

        <h3 class="cy-section-title mt-4 mb-2" style="font-size:.95rem">İstek hızı sınırı</h3>
        <p class="mb-0">
            Jeton başına <strong>60 saniyede 60 istek</strong>. Her yanıtta
            <code>X-RateLimit-Limit</code>, <code>X-RateLimit-Remaining</code> ve
            <code>X-RateLimit-Reset</code> başlıkları gelir. Sınır aşılırsa
            <code>429</code> ve <code>Retry-After</code> döner.
        </p>
    </div>
</div>

<!-- ==============================================================
     2) UÇ NOKTALAR
============================================================== -->
<div class="cy-card mt-3">
    <div class="cy-card__head">
        <h2 class="cy-section-title">
            <?= icon('users', 'cy-icon cy-icon--sm') ?> Uç Noktalar
        </h2>
    </div>

    <div class="cy-card__body">
        <?php foreach ($endpoints as $endpoint): ?>
            <div class="cy-endpoint">
                <div class="cy-endpoint__head">
                    <span class="cy-method cy-method--<?= e(strtolower($endpoint['method'])) ?>">
                        <?= e($endpoint['method']) ?>
                    </span>

                    <span class="cy-endpoint__path">/api/v1<?= e($endpoint['path']) ?></span>

                    <span class="cy-endpoint__scope">
                        <span class="cy-badge cy-badge--brand"><?= e($endpoint['scope']) ?></span>
                    </span>
                </div>

                <div class="cy-endpoint__body">
                    <p class="mb-0"><?= e($endpoint['summary']) ?></p>

                    <?php if (!empty($endpoint['params'])): ?>
                        <table class="cy-param-table">
                            <?php foreach ($endpoint['params'] as $name => $description): ?>
                                <tr>
                                    <td><code><?= e($name) ?></code></td>
                                    <td class="text-muted"><?= e($description) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>

                    <?php if (!empty($endpoint['request'])): ?>
                        <p class="cy-muted mt-3 mb-0" style="font-size:.75rem">İstek gövdesi</p>
                        <code class="cy-code"><?= e($endpoint['request']) ?></code>
                    <?php endif; ?>

                    <p class="cy-muted mt-3 mb-0" style="font-size:.75rem">Yanıt</p>
                    <code class="cy-code"><?= e($endpoint['response']) ?></code>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ==============================================================
     3) HATA KODLARI
============================================================== -->
<div class="cy-card mt-3">
    <div class="cy-card__head">
        <h2 class="cy-section-title">
            <?= icon('alert', 'cy-icon cy-icon--sm') ?> Hata Kodları
        </h2>
    </div>

    <div class="cy-card__body cy-card__body--flush">
        <div class="cy-table-wrap">
            <table class="table cy-table w-100">
                <thead>
                    <tr>
                        <th scope="col">HTTP</th>
                        <th scope="col">code</th>
                        <th scope="col">Anlamı</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    /* HTTP kodu ile "code" birlikte anlamlıdır: kod kabaca
                       "hangi katmanda sorun var" der, code ise tam olarak
                       neyin yanlış olduğunu söyler. */
                    $errors = [
                        ['400', 'invalid_json',        'İstek gövdesi geçerli bir JSON değil.'],
                        ['401', 'unauthenticated',     'Authorization başlığında Bearer jetonu yok.'],
                        ['401', 'invalid_token',       'Jeton geçersiz veya iptal edilmiş.'],
                        ['403', 'insufficient_scope',  'Jeton geçerli ama bu işlem için yetkisi yok.'],
                        ['403', 'self_delete',         'Jetonun sahibi kendi hesabını silemez.'],
                        ['404', 'not_found',           'Kaynak bulunamadı.'],
                        ['405', 'method_not_allowed',  'Adres var, HTTP metodu yanlış. Allow başlığına bakın.'],
                        ['409', 'email_taken',         'E-posta başka bir hesapta kayıtlı.'],
                        ['422', 'validation_failed',   'Alan doğrulaması başarısız. details alanına bakın.'],
                        ['422', 'nothing_to_update',   'PATCH isteğinde güncellenecek alan gönderilmedi.'],
                        ['429', 'rate_limit_exceeded', 'İstek sınırı aşıldı. Retry-After kadar bekleyin.'],
                    ];
                    ?>
                    <?php foreach ($errors as [$status, $code, $meaning]): ?>
                        <tr>
                            <td><code><?= e($status) ?></code></td>
                            <td><code><?= e($code) ?></code></td>
                            <td class="text-muted"><?= e($meaning) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
/* ÖRNEK KULLANIM — jeton sayfasıyla AYNI parça.
   Belgeler ile örnek kod ayrı yerlerde dursaydı biri güncellenip
   diğeri eskirdi. */
View::partial('partials/api_examples', [
    'ornekler'    => $ornekler ?? [],
    'ornekGercek' => false,
]);
