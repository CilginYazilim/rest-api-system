/* ==================================================================
 *  GİRİŞ EKRANI
 *  cilginyazilim.com
 * ------------------------------------------------------------------
 *  Küçük ama işe yarayan iki davranış:
 *    1. Demo hesap kartına tıklayınca formu doldurma
 *    2. Gönderirken butonu kilitleme (çift gönderimi önler)
 *
 *  Parolayı göster/gizle davranışı app.js içinde ortak olarak tanımlıdır.
 * ================================================================== */

/* global jQuery */
jQuery(function ($) {
    'use strict';

    var $form = $('#cy_login_form');

    if (!$form.length) { return; }

    /* ---- Demo hesap kartları ----
     * Yalnızca bu açık kaynak örneği deneyenler için vardır.
     * Gerçek projede hem bu kod hem de görünümdeki blok silinmelidir. */
    $('.js-demo-fill').on('click', function () {
        var $button = $(this);

        $('#email').val($button.data('email'));
        $('#password').val($button.data('password'));

        // Kullanıcı doldurulduğunu görsün ve tek hamlede giriş yapabilsin.
        $form.find('button[type="submit"]').trigger('focus');
    });

    /* ---- Çift gönderim koruması ----
     * Yavaş bağlantıda kullanıcı butona iki kez basabilir; bu da iki
     * giriş denemesi demektir ve kaba kuvvet sayacını boş yere artırır. */
    $form.on('submit', function () {
        var $button = $form.find('button[type="submit"]');

        $button.prop('disabled', true).addClass('disabled');

        // Sunucu hata döndürüp sayfa yeniden yüklenirse buton zaten
        // sıfırlanır. Yine de tarayıcı geri tuşu senaryosu için
        // kısa bir süre sonra tekrar açıyoruz.
        window.setTimeout(function () {
            $button.prop('disabled', false).removeClass('disabled');
        }, 6000);
    });
});
