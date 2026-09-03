<?php
/**
 * =====================================================================
 *  PARÇA: Örnek Kullanım
 * ---------------------------------------------------------------------
 *  Hem "API Jetonları" hem "API Belgeleri" sayfasında görünür. İki
 *  yerde de aynı metnin çıkması için ortak parça yapıldı; kopyalansaydı
 *  biri güncellenip diğeri unutulurdu.
 *
 *  @var array<int,array{anahtar:string,ad:string,dil:string,dosya:string,kod:string}> $ornekler
 *  @var bool $ornekGercek  Kodlarda GERÇEK jeton mu basılı?
 * =====================================================================
 */

/** @var array<int,array<string,string>> $ornekler */
$ornekler = $ornekler ?? [];

if ($ornekler === []) {
    return;
}

$gercek = !empty($ornekGercek);
?>

<div class="cy-card mt-3" id="ornek-kullanim">
    <div class="cy-card__head">
        <h2 class="cy-section-title">
            <?= icon('code', 'cy-icon cy-icon--sm') ?> Örnek Kullanım
        </h2>
    </div>

    <div class="cy-card__body">
        <p class="cy-muted mb-3">
            <?php if ($gercek): ?>
                Aşağıdaki kodlarda <strong>az önce ürettiğiniz jeton</strong> ve
                bu sunucunun gerçek adresi basılıdır — kopyalayıp doğrudan
                çalıştırabilirsiniz.
            <?php else: ?>
                Aşağıdaki kodlarda jeton yerine <strong>örnek bir değer</strong>
                vardır. Kendi jetonunuzu üretip <code>TOKEN</code> satırına
                yazın; adres bu sunucunun gerçek adresidir.
            <?php endif; ?>
        </p>

        <!--
            SEKMELER
            ------------------------------------------------------------
            Dört dili alt alta basmak sayfayı okunmaz hâle getiriyordu.
            Sekmeler JavaScript'siz de çalışsın diye radyo düğmesi
            kullanılabilirdi; burada sayfada zaten jQuery var, bu yüzden
            düğme + gizle/göster tercih edildi. JavaScript kapalıysa
            bütün bloklar görünür kalır — hiçbir içerik kaybolmaz.
        -->
        <div class="cy-tabs" role="tablist" aria-label="Programlama dili">
            <?php foreach ($ornekler as $sira => $ornek): ?>
                <button type="button"
                        class="cy-tab<?= $sira === 0 ? ' is-active' : '' ?>"
                        role="tab"
                        aria-selected="<?= $sira === 0 ? 'true' : 'false' ?>"
                        aria-controls="ornek-<?= e($ornek['anahtar']) ?>"
                        data-ornek-sekme="<?= e($ornek['anahtar']) ?>">
                    <?= e($ornek['ad']) ?>
                </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ($ornekler as $sira => $ornek): ?>
            <div class="cy-ornek" id="ornek-<?= e($ornek['anahtar']) ?>" role="tabpanel"
                 data-ornek-panel="<?= e($ornek['anahtar']) ?>"
                 <?= $sira === 0 ? '' : 'hidden' ?>>

                <div class="cy-ornek__bar">
                    <span class="cy-ornek__ad"><?= e($ornek['dosya']) ?></span>

                    <span class="cy-ornek__spacer"></span>

                    <button type="button" class="btn cy-btn cy-btn--sm cy-btn--ghost"
                            data-kopyala="#ornek-kod-<?= e($ornek['anahtar']) ?>">
                        Kopyala
                    </button>

                    <a class="btn cy-btn cy-btn--sm cy-btn--primary"
                       href="<?= e(url('tokens/ornek?dil=' . $ornek['anahtar'])) ?>">
                        <?= icon('download', 'cy-icon cy-icon--sm') ?> İndir
                    </a>
                </div>

                <pre class="cy-ornek__kod"><code id="ornek-kod-<?= e($ornek['anahtar']) ?>"><?= e($ornek['kod']) ?></code></pre>
            </div>
        <?php endforeach; ?>

        <p class="cy-muted mb-0" style="font-size:.8125rem">
            <strong>İndirilen dosyada jeton yazmaz.</strong> Yerine
            <code>BURAYA_JETONUNUZU_YAPISTIRIN</code> konur. Bir kimlik
            bilgisini dosyaya gömmek, onu “İndirilenler” klasöründe,
            yedeklerde ve er geç bir kod deposunda bırakmanın en kolay yoludur.
        </p>
    </div>
</div>
