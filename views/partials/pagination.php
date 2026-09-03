<?php
/**
 * =====================================================================
 *  PARÇA: Sayfalama çubuğu
 * ---------------------------------------------------------------------
 *  Tek bir Paginator nesnesinden hem "1-20 / 137 kayıt" özetini hem de
 *  sayfa bağlantılarını üretir. Listeleyen HER sayfa bunu kullanır;
 *  sayfalama görünümü tek yerden düzeltilir.
 *
 *  KULLANIMI (görünüm içinden):
 *      <?php View::partial('partials/pagination', [
 *          'paginator' => $paginator,
 *          'route'     => 'users',
 *          'query'     => ['q' => $search],   // korunacak filtreler
 *      ]); ?>
 *
 *  @var App\Core\Paginator            $paginator
 *  @var string                        $route Bağlantıların gideceği rota
 *  @var array<string,string|int>|null $query Korunacak sorgu parametreleri
 * =====================================================================
 */

/** @var App\Core\Paginator $paginator */
$query = $query ?? [];

/* Boş filtreleri adresten atıyoruz: "?q=&per=20" gibi anlamsız
 * uzunluklar hem çirkin hem de paylaşılan bağlantıyı okunmaz yapar. */
$query = array_filter($query, static fn ($value): bool => $value !== '' && $value !== null);
?>
<nav class="cy-pager" aria-label="Sayfalama">

    <!-- ---------- Sol: kayıt özeti ----------
         "Kaçıncı kayıttayım?" sorusunun yanıtı. Yalnızca sayfa
         numarası göstermek bu soruyu yanıtlamaz; toplam kayıt sayısı
         listenin büyüklüğünü de anlatır. -->
    <p class="cy-pager__info">
        <?php if ($paginator->total() === 0): ?>
            Kayıt bulunamadı
        <?php else: ?>
            <strong><?= number_format($paginator->from(), 0, ',', '.') ?></strong>–<strong><?= number_format($paginator->to(), 0, ',', '.') ?></strong>
            arası, toplam <strong><?= number_format($paginator->total(), 0, ',', '.') ?></strong> kayıt
        <?php endif; ?>
    </p>

    <?php if ($paginator->hasPages()): ?>
        <ul class="cy-pager__list">

            <?php /* ---------- Önceki ----------
                     İlk sayfadayken bağlantı DEĞİL <span> basıyoruz.
                     Tıklanamayan bir <a> hem klavye kullanıcısını
                     yanıltır hem de arama motoruna gereksiz adres verir. */ ?>
            <li>
                <?php if ($paginator->onFirstPage()): ?>
                    <span class="cy-pager__link is-disabled" aria-hidden="true">
                        <?= icon('chevron', 'cy-icon cy-icon--sm cy-pager__prev') ?>
                    </span>
                <?php else: ?>
                    <a class="cy-pager__link"
                       href="<?= e($paginator->url($paginator->currentPage() - 1, $route, $query)) ?>"
                       rel="prev" aria-label="Önceki sayfa">
                        <?= icon('chevron', 'cy-icon cy-icon--sm cy-pager__prev') ?>
                    </a>
                <?php endif; ?>
            </li>

            <?php /* ---------- Sayfa numaraları ----------
                     null gelen öğe "araya sayfa girdi" demektir; "…"
                     olarak basılır (bkz. Paginator::pages). */ ?>
            <?php foreach ($paginator->pages() as $page): ?>
                <li<?= $page === null ? '' : ' class="cy-pager__num"' ?>>
                    <?php if ($page === null): ?>
                        <span class="cy-pager__gap" aria-hidden="true">…</span>
                    <?php elseif ($page === $paginator->currentPage()): ?>
                        <span class="cy-pager__link is-active" aria-current="page"><?= $page ?></span>
                    <?php else: ?>
                        <a class="cy-pager__link"
                           href="<?= e($paginator->url($page, $route, $query)) ?>"
                           aria-label="<?= $page ?>. sayfa"><?= $page ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>

            <!-- ---------- Sonraki ---------- -->
            <li>
                <?php if ($paginator->onLastPage()): ?>
                    <span class="cy-pager__link is-disabled" aria-hidden="true">
                        <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
                    </span>
                <?php else: ?>
                    <a class="cy-pager__link"
                       href="<?= e($paginator->url($paginator->currentPage() + 1, $route, $query)) ?>"
                       rel="next" aria-label="Sonraki sayfa">
                        <?= icon('chevron', 'cy-icon cy-icon--sm') ?>
                    </a>
                <?php endif; ?>
            </li>
        </ul>

        <?php /* ---------- Mobil özet ----------
                 Telefonda numara listesi taşar; orada CSS bu listeyi
                 gizler ve yerine "3 / 12" özetini gösterir. */ ?>
        <p class="cy-pager__compact">
            <strong><?= $paginator->currentPage() ?></strong> / <?= $paginator->lastPage() ?>
        </p>
    <?php endif; ?>
</nav>
