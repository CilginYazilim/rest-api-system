/* ==================================================================
 *  CY ADMIN – PANEL KABUĞU
 *  cilginyazilim.com
 * ------------------------------------------------------------------
 *  Bu dosya her sayfada yüklenir ve şunları yönetir:
 *
 *    1. Genel yardımcılar  (CY.url, CY.notify, CY.escape)
 *    2. Sol menü           (mobil çekmece + masaüstü daraltma)
 *    3. Tema               (açık / koyu, çerezde saklanır)
 *    4. Bildirimler        (sunucudan gelen flash mesajları)
 *    5. Ortak form davranışları (parola göster, görsel önizleme)
 *
 *  Sayfaya özel kod ayrı dosyalardadır (users.js, login.js).
 *
 *  NEDEN SATIR İÇİ <script> KULLANMIYORUZ?
 *  Content-Security-Policy başlığımız satır içi script'e izin vermez.
 *  Bu, olası bir XSS açığında saldırganın kod çalıştırmasını
 *  engelleyen güçlü bir ikinci savunma hattıdır.
 * ================================================================== */

/* global bootstrap, jQuery */
window.CY = (function ($) {
    'use strict';

    var CY = {};

    /* =============================================================
     *  1) GENEL YARDIMCILAR
     * ============================================================= */

    /** Sayfadaki CSRF anahtarı. Her AJAX isteğine eklenir. */
    CY.token = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    /**
     * Uygulama içi adres üretir.
     *   CY.url('api/users/list')  →  "index.php?r=api/users/list"
     *
     * Kalıbı sunucu <meta name="cy-base"> ile bildirir; böylece
     * "temiz adres" ayarı açıldığında JavaScript'i değiştirmek
     * gerekmez.
     */
    CY.url = function (path) {
        var meta = document.querySelector('meta[name="cy-base"]');
        var tpl  = meta ? meta.getAttribute('content') : 'index.php?r=__PATH__';

        return tpl.replace('__PATH__', path);
    };

    /**
     * Uygulamaya POST isteği gönderir.
     *
     *   CY.post('api/preferences/theme', { theme: 'dark' })
     *
     * NEDEN AYRI BİR YARDIMCI?
     * Her çağrı noktasında adres kurmak ve CSRF anahtarını elle
     * eklemek gerekiyordu. Tek bir yerde toplayınca "bir yerde
     * csrf_token eklemeyi unutmak" diye bir ihtimal kalmıyor.
     *
     * Veri değiştiren istekler HER ZAMAN POST'tur; GET ile veri
     * değiştirmek, basit bir <img> etiketinin bile işlem
     * tetikleyebilmesi demektir.
     *
     * @param  {string} path Rota ("api/users/list")
     * @param  {Object} [data] Gönderilecek alanlar
     * @return {jqXHR}  Zincirlenebilir jQuery isteği
     */
    CY.post = function (path, data) {
        var payload = $.extend({}, data || {}, { csrf_token: CY.token() });

        return $.ajax({
            url: CY.url(path),
            type: 'POST',
            dataType: 'json',
            data: payload
        });
    };

    /**
     * Metni HTML'e güvenle koymak için kaçışlar.
     * Sunucudan gelen veriyi innerHTML ile basmak zorunda kaldığımız
     * nadir yerlerde kullanılır. Mümkün olan her yerde .text() tercih
     * edilmelidir; o zaten kaçışlama gerektirmez.
     */
    CY.escape = function (value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    };

    /** Çerez yazar (tema ve menü tercihi için). */
    CY.setCookie = function (name, value, days) {
        var expires = new Date(Date.now() + (days || 365) * 864e5).toUTCString();
        var secure  = location.protocol === 'https:' ? '; Secure' : '';

        document.cookie = name + '=' + encodeURIComponent(value) +
                          '; expires=' + expires + '; path=/; SameSite=Lax' + secure;
    };

    CY.getCookie = function (name) {
        var match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
        return match ? decodeURIComponent(match[2]) : '';
    };

    /* =============================================================
     *  BİLDİRİMLER (Toast)
     * ============================================================= */

    var TOAST_ICONS = {
        success: '<path d="M20 6 9 17l-5-5"/>',
        danger:  '<path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>',
        warning: '<path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="10"/>',
        info:    '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>'
    };

    /**
     * Sağ üstte geçici bildirim gösterir.
     * @param {string} message Gösterilecek metin
     * @param {string} [type]  success | danger | warning | info
     */
    CY.notify = function (message, type) {
        type = TOAST_ICONS[type] ? type : 'success';

        var $container = $('#cy_toasts');
        if (!$container.length) { return; }

        var $toast = $(
            '<div class="toast cy-toast cy-toast--' + type + '" role="alert" aria-live="assertive" aria-atomic="true">' +
                '<span class="cy-toast__icon">' +
                    '<svg class="cy-icon cy-icon--sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
                    'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + TOAST_ICONS[type] + '</svg>' +
                '</span>' +
                '<div class="cy-toast__body"></div>' +
                '<button type="button" class="btn-close btn-close-sm" data-bs-dismiss="toast" aria-label="Kapat"></button>' +
            '</div>'
        );

        // ÖNEMLİ: .html() değil .text(). Aksi halde mesajdaki HTML
        // çalışır ve XSS açığı oluşurdu.
        $toast.find('.cy-toast__body').text(message);
        $container.append($toast);

        var toast = new bootstrap.Toast($toast[0], { delay: 4500 });

        // Kapanınca DOM'dan tamamen kaldır (bellek sızıntısını önler).
        $toast.on('hidden.bs.toast', function () { $toast.remove(); });
        toast.show();
    };

    /**
     * AJAX hatasını kullanıcıya anlaşılır biçimde bildirir.
     * Oturum düştüyse (401/419) sayfayı yeniler; kullanıcı giriş
     * ekranına düşsün, "hiçbir şey çalışmıyor" hissi oluşmasın.
     */
    CY.ajaxError = function (xhr, fallback) {
        var res = (xhr && xhr.responseJSON) || {};

        if (xhr && (xhr.status === 401 || xhr.status === 419)) {
            CY.notify(res.description || 'Oturumunuz sonlandı. Sayfa yenileniyor…', 'warning');
            window.setTimeout(function () { window.location.reload(); }, 1500);
            return;
        }

        CY.notify(res.description || fallback || 'Bir hata oluştu.', 'danger');
    };

    /* =============================================================
     *  SAYFA HAZIR
     * ============================================================= */
    $(function () {

        /* ---------------------------------------------------------
         *  Tüm AJAX isteklerine CSRF anahtarını başlık olarak ekle.
         *  Böylece her istekte tek tek eklemeyi unutma riski kalkar.
         * ------------------------------------------------------- */
        $.ajaxSetup({
            headers: { 'X-CSRF-Token': CY.token() }
        });

        /* =========================================================
         *  2) SOL MENÜ
         * ---------------------------------------------------------
         *  Aynı düğme iki iş yapar:
         *    - Dar ekran (<992px) : menüyü çekmece olarak aç/kapat
         *    - Geniş ekran        : menüyü ikon moduna daralt/genişlet
         * ======================================================= */
        var $body     = $('body');
        var $backdrop = $('#cy_backdrop');
        var $toggle   = $('#cy_sidebar_toggle');
        var $sidebar  = $('#cy_sidebar');

        function isMobile() {
            return window.matchMedia('(max-width: 991.98px)').matches;
        }

        function openDrawer() {
            $body.addClass('is-sidebar-open');
            $backdrop.prop('hidden', false);
            $toggle.attr('aria-expanded', 'true');
        }

        function closeDrawer() {
            $body.removeClass('is-sidebar-open');
            $backdrop.prop('hidden', true);
            $toggle.attr('aria-expanded', 'false');
        }

        $toggle.on('click', function () {
            if (isMobile()) {
                $body.hasClass('is-sidebar-open') ? closeDrawer() : openDrawer();
                return;
            }

            // Masaüstü: daraltma tercihini çereze yaz ki sunucu bir
            // sonraki sayfayı doğrudan doğru genişlikte üretsin
            // (sayfa açılırken menünün "zıplamasını" önler).
            var collapsed = !$body.hasClass('is-collapsed');

            $body.toggleClass('is-collapsed', collapsed);
            CY.setCookie('cy_sidebar', collapsed ? 'collapsed' : 'expanded');
        });

        $backdrop.on('click', closeDrawer);

        // Menüden bir bağlantıya tıklanınca çekmece kapansın.
        $sidebar.on('click', 'a', function () {
            if (isMobile()) { closeDrawer(); }
        });

        /* --- Alt çubuktaki "Menü" düğmesi ---
         * Alt çubukta yer olmayan sayfalara (Sistem gibi) sol
         * çekmeceden ulaşılır. */
        $('#cy_bottomnav_more').on('click', function () {
            $body.hasClass('is-sidebar-open') ? closeDrawer() : openDrawer();
            $(this).attr('aria-expanded', $body.hasClass('is-sidebar-open') ? 'true' : 'false');
        });

        /* ---------------------------------------------------------
         *  ODAK TUZAĞI (focus trap)
         * ---------------------------------------------------------
         *  Çekmece açıkken TAB tuşu arkadaki sayfaya kaçmamalıdır.
         *  Kaçarsa ekran okuyucu kullanan biri, gördüğü karartma
         *  katmanının ARDINDAKİ görünmez bağlantılar arasında
         *  dolaşmaya başlar ve nerede olduğunu kaybeder.
         *
         *  Odağı çekmecenin ilk ve son odaklanabilir öğesi arasında
         *  döndürerek bunu engelliyoruz.
         * ------------------------------------------------------- */
        var FOCUSABLE = 'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])';

        $(document).on('keydown', function (event) {
            if (event.key !== 'Tab' || !$body.hasClass('is-sidebar-open')) { return; }

            var $items = $sidebar.find(FOCUSABLE).filter(':visible');
            if (!$items.length) { return; }

            var first = $items[0];
            var last  = $items[$items.length - 1];

            // Shift+Tab ilk öğedeyken → sona sar; Tab son öğedeyken → başa sar.
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        });

        /* ---------------------------------------------------------
         *  PARMAKLA AÇMA / KAPATMA (swipe)
         * ---------------------------------------------------------
         *  Telefon uygulamalarında beklenen davranış budur:
         *    - Sol kenardan sağa çek  → menü açılır
         *    - Menü açıkken sola çek  → menü kapanır
         *
         *  Neden Pointer Events? touchstart/mousedown ikilisini ayrı
         *  ayrı yazmak yerine tek olay kümesi parmağı, kalemi ve
         *  fareyi birlikte karşılar.
         *
         *  DİKEY KAYDIRMAYI BOZMAMAK için hareketin yönüne bakıyoruz:
         *  yatay fark dikeyden büyük değilse kullanıcı sayfayı
         *  kaydırıyordur, karışmıyoruz.
         * ------------------------------------------------------- */
        var EDGE_ZONE  = 24;   // px – sol kenarın "açma" şeridi
        var THRESHOLD  = 60;   // px – karar için gereken en az yatay hareket
        var swipe      = null;

        function onPointerDown(event) {
            if (!isMobile() || event.pointerType === 'mouse') { return; }

            var open = $body.hasClass('is-sidebar-open');

            // Kapalıyken yalnızca sol kenardan başlayan hareketi dinle;
            // aksi halde sayfadaki her yatay kaydırma menüyü açardı.
            if (!open && event.clientX > EDGE_ZONE) { return; }

            swipe = { x: event.clientX, y: event.clientY, open: open, locked: false };
        }

        function onPointerMove(event) {
            if (!swipe) { return; }

            var dx = event.clientX - swipe.x;
            var dy = event.clientY - swipe.y;

            // Yön kilidi: ilk belirgin hareket dikeyse hareketi bırak.
            if (!swipe.locked) {
                if (Math.abs(dx) < 10 && Math.abs(dy) < 10) { return; }

                if (Math.abs(dy) > Math.abs(dx)) { swipe = null; return; }

                swipe.locked = true;
                $sidebar.addClass('is-dragging');
            }

            if (!swipe.open && dx > THRESHOLD) {
                openDrawer();
                endSwipe();
            } else if (swipe.open && dx < -THRESHOLD) {
                closeDrawer();
                endSwipe();
            }
        }

        function endSwipe() {
            swipe = null;
            $sidebar.removeClass('is-dragging');
        }

        if (window.PointerEvent) {
            document.addEventListener('pointerdown', onPointerDown, { passive: true });
            document.addEventListener('pointermove', onPointerMove, { passive: true });
            document.addEventListener('pointerup', endSwipe, { passive: true });
            document.addEventListener('pointercancel', endSwipe, { passive: true });
        }

        // ESC tuşu çekmeceyi kapatır (klavye erişilebilirliği).
        $(document).on('keydown', function (event) {
            if (event.key === 'Escape' && $body.hasClass('is-sidebar-open')) {
                closeDrawer();
                $toggle.trigger('focus');
            }
        });

        // Ekran büyütülürse açık kalan çekmeceyi temizle.
        $(window).on('resize', function () {
            if (!isMobile() && $body.hasClass('is-sidebar-open')) {
                closeDrawer();
            }
        });

        /* =========================================================
         *  3) TEMA (açık / koyu)
         * ---------------------------------------------------------
         *  Tercih İKİ yere birden yazılır ve her birinin ayrı işi var:
         *
         *   - VERİTABANI (hesap): asıl kayıt burasıdır. Kullanıcı
         *     başka bir cihazdan girdiğinde de kendi temasını bulur.
         *
         *   - ÇEREZ: yalnızca giriş YAPMAMIŞ ziyaretçi için (giriş
         *     ekranı). Ayrıca sunucunun ilk HTML'i doğru temayla
         *     üretmesine yardım eder; sayfa açılırken bir an yanlış
         *     temada görünüp zıplamaz ("flash of wrong theme").
         *
         *  Sunucu isteği <html data-cy-theme="..."> ile zaten doğru
         *  temada gönderir; buradaki kod yalnızca DEĞİŞİMİ yönetir.
         * ======================================================= */
        var $themeToggle = $('#cy_theme_toggle');

        function currentTheme() {
            /* Sunucu bu özniteliği HER ZAMAN basar (varsayılan "light").
             * İşletim sisteminin koyu mod ayarına bilerek bakmıyoruz:
             * varsayılanın öngörülebilir olması tahmin etmekten iyidir. */
            return document.documentElement.getAttribute('data-cy-theme') === 'dark'
                ? 'dark'
                : 'light';
        }

        function paintThemeIcon(theme) {
            $('.cy-theme-icon--light').toggleClass('d-none', theme === 'dark');
            $('.cy-theme-icon--dark').toggleClass('d-none', theme !== 'dark');
        }

        /**
         * Mobil tarayıcının kendi çubuğunu (adres/durum çubuğu) tema
         * rengine boyar.
         *
         * Sunucu <meta name="theme-color"> etiketini zaten doğru
         * üretir; burada yalnızca kullanıcı temayı DEĞİŞTİRDİĞİNDE
         * güncelliyoruz. Güncellemezsek koyu temaya geçen kullanıcı,
         * sayfa siyahken tarayıcı çubuğu beyaz kalan yarım bir görüntü
         * görür — telefonda en çok göze batan ayrıntılardan biridir.
         */
        function paintBrowserChrome(theme) {
            var meta = document.querySelector('meta[name="theme-color"]');
            if (meta) { meta.setAttribute('content', theme === 'dark' ? '#0f172a' : '#ffffff'); }
        }

        paintThemeIcon(currentTheme());

        $themeToggle.on('click', function () {
            var next = currentTheme() === 'dark' ? 'light' : 'dark';

            /* Önce ekranı değiştiriyoruz: kullanıcı sunucunun yanıtını
             * beklemez, tema anında döner. Kayıt arkada yapılır. */
            document.documentElement.setAttribute('data-cy-theme', next);
            CY.setCookie('cy_theme', next);
            paintThemeIcon(next);
            paintBrowserChrome(next);

            /* Hesaba kaydet. Giriş yapılmamışsa (giriş ekranı) bu uç
             * nokta yoktur; hata sessizce yutulur, çerez yine de işini
             * görür. Kullanıcıyı ilgilendirmeyen bir ayrıntı için
             * ekrana hata basmıyoruz. */
            if (!$themeToggle.data('persist')) {
                return;
            }

            CY.post('api/preferences/theme', { theme: next }).fail(function () {
                /* Sessiz geç: tema ekranda zaten değişti. Bir sonraki
                 * sayfada eski temaya dönerse kullanıcı tekrar tıklar. */
            });
        });

        /* =========================================================
         *  4) SUNUCUDAN GELEN BİLDİRİMLER
         * ---------------------------------------------------------
         *  Flash mesajları <script type="application/json"> içinde
         *  taşınır. Tarayıcı bu etiketi ÇALIŞTIRMAZ, sadece veri
         *  olarak saklar; satır içi script yasağıyla uyumludur.
         * ======================================================= */
        var flashNode = document.getElementById('cy_flash');

        if (flashNode) {
            try {
                var messages = JSON.parse(flashNode.textContent || '[]');

                messages.forEach(function (item, index) {
                    // Mesajları hafif gecikmeli sırala; üst üste binmesinler.
                    window.setTimeout(function () {
                        CY.notify(item.message, item.type);
                    }, index * 220);
                });
            } catch (error) {
                // Bozuk JSON uygulamayı çökertmesin.
            }
        }

        /* =========================================================
         *  5) ORTAK FORM DAVRANIŞLARI
         * ======================================================= */

        /* --- Parolayı göster / gizle ---
         * Olay, sabit bir üst elemana bağlanır ("event delegation");
         * böylece modal içinde SONRADAN oluşan alanlarda da çalışır. */
        $(document).on('click', '.js-toggle-password', function () {
            var $button = $(this);
            var $input  = $button.siblings('input');

            if (!$input.length) { return; }

            var show = $input.attr('type') === 'password';

            $input.attr('type', show ? 'text' : 'password');
            $button.attr('aria-label', show ? 'Parolayı gizle' : 'Parolayı göster');
        });

        /* --- Görsel önizleme ---
         * FileReader dosyayı SUNUCUYA YÜKLEMEDEN tarayıcıda okur.
         * Sonuç "data:image/png;base64,..." biçiminde bir metindir. */
        $(document).on('change', '.js-image-input', function () {
            var input   = this;
            var file    = input.files && input.files[0];
            var $preview     = $($(input).data('preview'));
            var $placeholder = $($(input).data('placeholder'));

            if (!file || !$preview.length) { return; }

            var reader = new FileReader();

            reader.onload = function (event) {
                $preview.attr('src', event.target.result).removeClass('d-none');
                $placeholder.addClass('d-none');
            };

            reader.readAsDataURL(file);
        });
    });

    return CY;
})(jQuery);
