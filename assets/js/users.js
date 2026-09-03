/* ==================================================================
 *  KULLANICILAR – SAYFA KODU
 *  cilginyazilim.com – REST API Sistemi
 * ------------------------------------------------------------------
 *  FİLTRELER "UYGULA"YA BASMADAN ÇALIŞIR.
 *
 *  NEDEN AJAX DEĞİL?
 *  Bu sayfanın tamamı, filtrenin ve sayfa numarasının ADRES ÇUBUĞUNDA
 *  taşınması üzerine kurulu: bağlantı paylaşılabiliyor, geri tuşu
 *  çalışıyor, sayfa yenilenince aynı sonuç geliyor. Ajax ile tabloyu
 *  yerinde değiştirmek bu üç özelliği de elle yeniden yazmayı
 *  gerektirirdi. Formu kendiliğinden göndermek aynı sonucu verir ve
 *  sunucu tarafında tek satır değişiklik istemez.
 *
 *  JAVASCRIPT KAPALIYSA?
 *  Hiçbir şey bozulmaz: "Uygula" düğmesi yerinde durur ve form
 *  normal şekilde gönderilir. Düğme yalnızca JS çalışıyorken gizlenir,
 *  çünkü o durumda işlevsizdir.
 * ================================================================== */

/* global jQuery */
(function ($) {
    'use strict';

    var $form = $('form.cy-toolbar');

    if ($form.length === 0) {
        return;
    }

    /* ---------------------------------------------------------------
     *  1) "UYGULA" DÜĞMESİNİ GİZLE
     * ---------------------------------------------------------------
     *  Kaldırmıyoruz, gizliyoruz: form gönderimi hâlâ ona bağlı
     *  olabilir ve JS bir hata verirse düğme geri gelmelidir.
     * ------------------------------------------------------------- */
    $form.addClass('is-live');

    /* ---------------------------------------------------------------
     *  2) AÇILIR LİSTELER: ANINDA
     * ---------------------------------------------------------------
     *  Durum ve sayfa boyutu tek tıkla seçilir; beklemenin anlamı yok.
     * ------------------------------------------------------------- */
    $form.on('change', 'select', function () {
        gonder();
    });

    /* ---------------------------------------------------------------
     *  3) ARAMA KUTUSU: BEKLEMELİ (debounce)
     * ---------------------------------------------------------------
     *  Her tuş vuruşunda göndermek, "ahmet" yazan birine altı istek
     *  attırır ve beşinin yanıtı çöpe gider. 450 ms yazmayı bekliyoruz;
     *  kullanıcı duraksadığında tek istek gider.
     * ------------------------------------------------------------- */
    var sayac  = null;
    var $arama = $form.find('input[name="q"]');
    var sonDeger = $arama.val();

    $arama.on('input', function () {
        window.clearTimeout(sayac);

        sayac = window.setTimeout(function () {
            // Değer gerçekten değiştiyse gönder: ok tuşları ve
            // yapıştırıp geri alma gibi durumlarda boşuna istek gitmesin.
            if ($arama.val() !== sonDeger) {
                gonder();
            }
        }, 450);
    });

    /* Enter'a basılırsa beklemeyi iptal edip hemen gönder; yoksa
       form iki kez gönderilmiş gibi görünüyordu. */
    $arama.on('keydown', function (event) {
        if (event.key === 'Enter') {
            window.clearTimeout(sayac);
        }
    });

    /* type="search" kutusundaki (x) düğmesi "input" değil "search"
       olayı üretir; onu yakalamazsak temizleme çalışmıyor görünür. */
    $arama.on('search', function () {
        window.clearTimeout(sayac);
        gonder();
    });

    /* ---------------------------------------------------------------
     *  4) İMLECİ GERİ GETİR
     * ---------------------------------------------------------------
     *  Sayfa yeniden yüklendiği için odak kayboluyor ve kullanıcı
     *  yazmaya devam edemiyordu. Aramayla geldiysek kutuya odaklanıp
     *  imleci metnin SONUNA koyuyoruz (başına koyarsa yazdıkça metin
     *  ters diziliyor).
     * ------------------------------------------------------------- */
    var deger = String($arama.val() || '');

    if (deger !== '') {
        $arama.trigger('focus');

        var alan = $arama.get(0);

        if (alan && typeof alan.setSelectionRange === 'function') {
            try {
                alan.setSelectionRange(deger.length, deger.length);
            } catch (hata) {
                /* type="search" bazı tarayıcılarda seçim aralığını
                   desteklemez; odak yine de yerinde kalır. */
            }
        }
    }

    function gonder() {
        /* requestSubmit, submit olayını tetikler (submit() tetiklemez):
           forma bağlı başka bir dinleyici varsa o da çalışsın. */
        var form = $form.get(0);

        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit();
            return;
        }

        form.submit();
    }
})(jQuery);
