<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: API jetonları
 * ---------------------------------------------------------------------
 *  @var array<int,App\Models\ApiToken> $tokens
 *  @var App\Core\Paginator             $paginator
 *  @var string|null                    $freshToken Yeni üretilen jeton
 * =====================================================================
 */

use App\Core\View;
?>

<?php if (!empty($freshToken)): ?>
    <!-- ==========================================================
         YENİ JETON – YALNIZCA BİR KEZ
         ----------------------------------------------------------
         Jeton veritabanında yalnızca ÖZETİ ile durur; düz hâli
         hiçbir yerde saklanmaz. Bu kutu sayfa yenilendiğinde
         kaybolur ve jeton bir daha gösterilemez.
    ========================================================== -->
    <div class="cy-card cy-fresh-token mb-3">
        <div class="cy-card__body">
            <h2 class="cy-section-title mb-2">
                <?= icon('check', 'cy-icon cy-icon--sm') ?> Jetonunuz hazır
            </h2>

            <p class="cy-muted mb-3">
                Bu değeri <strong>şimdi kopyalayın</strong>. Güvenlik gereği
                veritabanında yalnızca özeti saklanır; sayfayı yenilediğinizde
                bir daha göremezsiniz.
            </p>

            <div class="cy-token-box">
                <code id="fresh_token"><?= e($freshToken) ?></code>

                <button type="button" class="btn cy-btn cy-btn--sm cy-btn--primary" id="copy_token"
                        data-target="#fresh_token">
                    Kopyala
                </button>
            </div>

            <p class="cy-muted mt-3 mb-0" style="font-size:.8125rem">
                Kullanımı:
                <code>curl -H "Authorization: Bearer &lt;jeton&gt;" &lt;api adresi&gt;</code>
            </p>
        </div>
    </div>
<?php endif; ?>

<?php
/* ÖRNEK KULLANIM
   Jeton üretmek yetmiyordu: kullanıcı jetonu alıp "peki şimdi ne
   yapacağım?" sorusuyla kalıyordu. Çalışır durumdaki örnekler jetonun
   hemen altında duruyor. Aynı parça API Belgeleri sayfasında da var. */
View::partial('partials/api_examples', [
    'ornekler'    => $ornekler ?? [],
    'ornekGercek' => $ornekGercek ?? false,
]);
?>

<!-- ==============================================================
     YENİ JETON ÜRETME
============================================================== -->
<div class="cy-card">
    <div class="cy-card__head">
        <h2 class="cy-section-title">
            <?= icon('plus', 'cy-icon cy-icon--sm') ?> Yeni Jeton
        </h2>
    </div>

    <div class="cy-card__body">
        <form method="post" action="<?= e(url('tokens')) ?>" class="cy-token-form">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="token_name">Jeton adı</label>
                <input type="text" class="form-control" id="token_name" name="name"
                       placeholder="Mobil uygulama, Rapor betiği…" maxlength="100" required>
                <div class="form-text">
                    Ad yalnızca sizin içindir; hangi jetonun nerede kullanıldığını
                    hatırlamanızı sağlar.
                </div>
            </div>

            <!--
                YETKİ KAPSAMLARI (scopes)
                ------------------------------------------------------
                "Her şeyi yapabilen" bir jeton, sızdığında sınırsız
                zarar verir. Yalnızca rapor çeken bir betiğe okuma
                yetkisi vermek, EN AZ YETKİ ilkesinin en basit
                uygulamasıdır.
            -->
            <fieldset class="mb-3">
                <legend class="form-label">Yetkiler</legend>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="scope_read"
                           name="scope_read" value="1" checked>
                    <label class="form-check-label" for="scope_read">
                        <strong>read</strong> — listeleme ve okuma (GET)
                    </label>
                </div>

                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="scope_write"
                           name="scope_write" value="1">
                    <label class="form-check-label" for="scope_write">
                        <strong>write</strong> — oluşturma, güncelleme, silme (POST · PATCH · DELETE)
                    </label>
                </div>
            </fieldset>

            <button type="submit" class="btn cy-btn cy-btn--primary">
                <?= icon('plus', 'cy-icon cy-icon--sm') ?> Jeton üret
            </button>
        </form>
    </div>
</div>

<!-- ==============================================================
     JETON LİSTESİ (sayfalanmış)
============================================================== -->
<div class="cy-card mt-3">
    <div class="cy-card__head">
        <h2 class="cy-section-title">
            <?= icon('lock', 'cy-icon cy-icon--sm') ?> Jetonlarım
        </h2>
    </div>

    <div class="cy-card__body cy-card__body--flush">
        <div class="cy-table-wrap">
            <table class="table cy-table w-100">
                <thead>
                    <tr>
                        <th scope="col">Ad</th>
                        <th scope="col" class="cy-hide-sm">Yetkiler</th>
                        <th scope="col" class="cy-hide-sm">Son Kullanım</th>
                        <th scope="col">Durum</th>
                        <th scope="col" class="text-end">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($tokens === []): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                Henüz jeton üretmediniz.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($tokens as $token): ?>
                        <tr>
                            <td>
                                <div class="cy-user-cell">
                                    <span class="cy-user-cell__name"><?= e($token->name) ?></span>
                                    <span class="cy-user-cell__meta">
                                        <?= e(implode(' · ', $token->scopes)) ?> ·
                                        <?= e(human_date($token->createdAt)) ?>
                                    </span>
                                </div>
                            </td>

                            <td class="cy-hide-sm">
                                <?php foreach ($token->scopes as $scope): ?>
                                    <span class="cy-badge cy-badge--brand"><?= e($scope) ?></span>
                                <?php endforeach; ?>
                            </td>

                            <td class="cy-hide-sm">
                                <?php /* Hiç kullanılmamış jetonlar, unutulup ortalıkta
                                         kalan yetkilerdir; görünür olmaları iyidir. */ ?>
                                <?= $token->lastUsedAt === null
                                    ? '<span class="cy-muted">hiç kullanılmadı</span>'
                                    : e(human_date($token->lastUsedAt)) ?>
                            </td>

                            <td>
                                <?php if ($token->isRevoked()): ?>
                                    <span class="cy-status is-passive">
                                        <span class="cy-status__dot"></span> İptal
                                    </span>
                                <?php else: ?>
                                    <span class="cy-status is-active">
                                        <span class="cy-status__dot"></span> Etkin
                                    </span>
                                <?php endif; ?>
                            </td>

                            <td class="text-end">
                                <?php if (!$token->isRevoked()): ?>
                                    <?php /* İPTAL NEDEN FORM (POST)?
                                             Bağlantı (GET) olsaydı, bir <img> etiketi
                                             veya arama motoru botu jetonlarınızı
                                             iptal edebilirdi. */ ?>
                                    <form method="post" action="<?= e(url('tokens/revoke')) ?>" class="d-inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= (int) $token->id ?>">
                                        <button type="submit" class="btn cy-btn cy-btn--sm cy-btn--danger"
                                                data-confirm="Bu jeton iptal edilsin mi? Kullanan uygulamalar erişimini kaybeder.">
                                            İptal et
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="cy-muted">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php View::partial('partials/pagination', [
            'paginator' => $paginator,
            'route'     => 'tokens',
        ]); ?>
    </div>
</div>
