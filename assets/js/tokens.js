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
     *  2) İPTAL ONAYI
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
