<?php
/**
 * =====================================================================
 *  GÖRÜNÜM: Kontrol paneli
 * ---------------------------------------------------------------------
 *  Üç bölümden oluşur:
 *      1. Sayısal özet kartları
 *      2. Projeye özel bölüm  (views/dashboard/_feature.php)
 *      3. Son işlemler (audit log)
 *
 *  2. bölüm İSTEĞE BAĞLIDIR: dosya varsa basılır, yoksa atlanır.
 *  Böylece bu düzen dosyası tüm örnek projelerde AYNI kalır; her
 *  proje yalnızca kendi kartını yazar.
 *
 *  @var array<int,array{label:string,value:int,icon:string}> $stats
 *  @var array<int,array<string,mixed>>                       $activity
 * =====================================================================
 */

use App\Core\View;

/**
 * İşlem türünü ikon ve renge eşler.
 * Görsel dil tutarlı olsun diye tek yerde tanımlıyoruz.
 *
 * @return array{0:string,1:string}
 */
$actionStyle = static function (string $action): array {
    return match ($action) {
        'login'            => ['logout', 'success'],
        'logout'           => ['logout', 'info'],
        'login_failed'     => ['alert', 'danger'],
        'password_changed' => ['lock', 'warning'],
        default            => ['activity', 'info'],
    };
};

/* Renk sırası: kartlar farklı renkte olsun diye döngüsel kullanılır. */
$tones = ['brand', 'success', 'warning', 'danger'];
?>

<!-- ============ 1) SAYISAL ÖZET ============ -->
<div class="cy-stats">
    <?php foreach ($stats as $index => $stat): ?>
        <div class="cy-stat">
            <span class="cy-stat__icon cy-stat__icon--<?= e($tones[$index % count($tones)]) ?>">
                <?= icon($stat['icon']) ?>
            </span>
            <span>
                <span class="cy-stat__label"><?= e($stat['label']) ?></span>
                <span class="cy-stat__value"><?= number_format((int) $stat['value'], 0, ',', '.') ?></span>
                <?php if (isset($stat['hint'])): ?>
                    <span class="cy-stat__hint"><?= e($stat['hint']) ?></span>
                <?php endif; ?>
            </span>
        </div>
    <?php endforeach; ?>
</div>

<!-- ============ 2) PROJEYE ÖZEL BÖLÜM ============ -->
<?php if (is_file(CY_BASE . '/views/dashboard/_feature.php')): ?>
    <?php View::partial('dashboard/_feature'); ?>
<?php endif; ?>

<!-- ============ 3) SON İŞLEMLER ============ -->
<div class="cy-card mt-3">
    <div class="cy-card__head">
        <h2 class="cy-section-title"><?= icon('activity', 'cy-icon cy-icon--sm') ?> Son İşlemler</h2>
    </div>

    <div class="cy-card__body cy-card__body--flush">
        <div class="cy-timeline">
            <?php if ($activity === []): ?>
                <p class="p-4 mb-0 text-center text-muted">Henüz kayıt yok.</p>
            <?php endif; ?>

            <?php foreach ($activity as $row): ?>
                <?php [$actionIcon, $tone] = $actionStyle((string) $row['action']); ?>
                <div class="cy-timeline__item">
                    <span class="cy-timeline__icon cy-timeline__icon--<?= e($tone) ?>">
                        <?= icon($actionIcon, 'cy-icon cy-icon--sm') ?>
                    </span>

                    <div class="cy-timeline__body">
                        <p class="cy-timeline__text"><?= e($row['description']) ?></p>
                        <span class="cy-timeline__meta">
                            <?php
                            /* Kullanıcı silinmişse LEFT JOIN null döndürür;
                               satır yine de listede kalsın diye "—" basıyoruz. */
                            $who = trim((string) ($row['name'] ?? '') . ' ' . (string) ($row['surname'] ?? ''));
                            ?>
                            <?= e($who !== '' ? $who : 'Bilinmeyen') ?> ·
                            <?= e(human_date((string) $row['created_at'])) ?>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
