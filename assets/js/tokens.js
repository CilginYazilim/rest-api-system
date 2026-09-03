/* ==================================================================
 *  API JETONLARI – SAYFA KODU
 *  cilginyazilim.com – REST API Sistemi
 * ------------------------------------------------------------------
 *  İki küçük iş yapar:
 *    1. Yeni üretilen jetonu panoya kopyalar
 *    2. İptal düğmelerinde onay ister
 * ================================================================== */

/* global CY, jQuery */
(function ($) {
    'use strict';

    /* ---------------------------------------------------------------
     *  1) PANOYA KOPYALA
     * ---------------------------------------------------------------
     *  navigator.clipboard yalnızca GÜVENLİ BAĞLAMDA (https veya
     *  localhost) çalışır. Yoksa eski yönteme düşüyoruz: metni seçip
     *  document.execCommand('copy') çağırmak. Eski yöntem
     *  kullanımdan kaldırıldı ama hâlâ her yerde çalışıyor ve
     *  "kopyalanamadı" demekten iyidir.
     * ------------------------------------------------------------- */
    $('#copy_token').on('click', function () {
        var $button = $(this);
        var text    = $($button.data('target')).text().trim();

        function done() {
            var original = $button.text();

            $button.text('Kopyalandı');
            CY.notify('Jeton panoya kopyalandı. Güvenli bir yerde saklayın.', 'success');

            window.setTimeout(function () { $button.text(original); }, 2000);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(fallback);
            return;
        }

        fallback();

        function fallback() {
            var area = document.createElement('textarea');

            area.value = text;

            /* Ekranda görünmesin ama seçilebilsin: display:none
             * kullanırsak seçim çalışmaz. */
            area.style.position = 'fixed';
            area.style.opacity  = '0';

            document.body.appendChild(area);
            area.select();

            try {
                document.execCommand('copy');
                done();
            } catch (error) {
                CY.notify('Kopyalanamadı. Metni elle seçip kopyalayın.', 'warning');
            }

            document.body.removeChild(area);
        }
    });

    /* ---------------------------------------------------------------
     *  2) ÖRNEK KULLANIM SEKMELERİ
     * ---------------------------------------------------------------
     *  Dört dil alt alta basılınca sayfa okunmaz hâle geliyordu.
     *  Paneller sunucuda basılır ve burada yalnızca GÖSTERİLİP
     *  GİZLENİR: JavaScript çalışmazsa hepsi görünür kalır, hiçbir
     *  içerik kaybolmaz.
     * ------------------------------------------------------------- */
    $(document).on('click', '[data-ornek-sekme]', function () {
        var anahtar = $(this).data('ornek-sekme');

        $('[data-ornek-sekme]')
            .removeClass('is-active')
            .attr('aria-selected', 'false');

        $(this).addClass('is-active').attr('aria-selected', 'true');

        $('[data-ornek-panel]').each(function () {
            this.hidden = $(this).data('ornek-panel') !== anahtar;
        });
    });

    /* ---------------------------------------------------------------
     *  3) KOD KOPYALA
     * ---------------------------------------------------------------
     *  Jeton kutusundaki kopyalama ile aynı iş; tek fark hedefin
     *  data-kopyala ile verilmesi. İkisi de aynı yedek yolu kullanır.
     * ------------------------------------------------------------- */
    $(document).on('click', '[data-kopyala]', function () {
        var $button = $(this);
        var metin   = $($button.data('kopyala')).text();

        function bitti() {
            var eski = $button.text();

            $button.text('Kopyalandı');
            CY.notify('Örnek kod panoya kopyalandı.', 'success');

            window.setTimeout(function () { $button.text(eski); }, 2000);
        }

        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(metin).then(bitti).catch(function () {
                CY.notify('Kopyalanamadı. Metni elle seçip kopyalayın.', 'warning');
            });

            return;
        }

        var alan = document.createElement('textarea');

        alan.value          = metin;
        alan.style.position = 'fixed';
        alan.style.opacity  = '0';

        document.body.appendChild(alan);
        alan.select();

        try {
            document.execCommand('copy');
            bitti();
        } catch (error) {
            CY.notify('Kopyalanamadı. Metni elle seçip kopyalayın.', 'warning');
        }

        document.body.removeChild(alan);
    });

    /* ---------------------------------------------------------------
     *  4) JETON DETAY MODALI
     * ---------------------------------------------------------------
     *  Sayfada TEK modal var; hangi satırın düğmesine basıldıysa
     *  içerik oradan doldurulur. Her satır için ayrı modal basmak,
     *  10 jetonda 10 kopya HTML demekti.
     *
     *  Jetonun KENDİSİ burada yok ve olamaz: veritabanında yalnızca
     *  SHA-256 özeti duruyor. Modal, elimizde OLAN her şeyi gösterir.
     * ------------------------------------------------------------- */
    $(document).on('click', '[data-jeton-detay]', function () {
        var $dugme = $(this);

        $('#jd_ad').text($dugme.data('ad'));
        $('#jd_id').text('#' + $dugme.data('id'));
        $('#jd_kapsam').text($dugme.data('kapsam'));
        $('#jd_istek').text($dugme.data('istek'));
        $('#jd_olusturma').text($dugme.data('olusturma'));
        $('#jd_kullanim').text($dugme.data('kullanim'));
        $('#jd_ip').text($dugme.data('ip'));
        $('#jd_durum').text($dugme.data('durum'));

        var iptal = String($dugme.data('iptal') || '');

        $('#jd_iptal').text(iptal);
        document.getElementById('jd_iptal_satir').hidden = iptal === '';

        window.bootstrap.Modal.getOrCreateInstance(document.getElementById('jetonDetayModal')).show();
    });

    /* ---------------------------------------------------------------
     *  5) İPTAL ONAYI
     * ---------------------------------------------------------------
     *  Jeton iptali GERİ ALINAMAZ: o jetonu kullanan uygulamalar
     *  anında erişimini kaybeder. Tek tıkla olmasına izin vermiyoruz.
     * ------------------------------------------------------------- */
    $(document).on('click', '[data-confirm]', function (event) {
        if (!window.confirm($(this).data('confirm'))) {
            event.preventDefault();
        }
    });
})(jQuery);
