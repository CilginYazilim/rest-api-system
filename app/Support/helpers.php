<?php
/**
 * =====================================================================
 *  Yardımcı fonksiyonlar
 * ---------------------------------------------------------------------
 *  Görünüm dosyalarında sınıf adı yazmak yorucudur. En sık kullanılan
 *  birkaç işi kısa global fonksiyonlara indirgedik:
 *
 *      <?= e($user->name) ?>            yerine htmlspecialchars(...)
 *      <a href="<?= url('users') ?>">   yerine elle adres kurma
 *
 *  Bu dosya index.php tarafından bir kez dahil edilir.
 * =====================================================================
 */

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Csrf;

if (!function_exists('e')) {
    /**
     * Metni HTML'e GÜVENLE basmak için kaçışlar (XSS koruması).
     *
     * XSS NEDİR? Kullanıcı ad alanına <script>alert(1)</script> yazarsa
     * ve biz bunu ekrana olduğu gibi basarsak tarayıcı bunu KOD olarak
     * çalıştırır; saldırgan oturum bilgilerini ele geçirebilir.
     *
     * htmlspecialchars bu karakterleri zararsızlaştırır:
     *   <  →  &lt;    >  →  &gt;    "  →  &quot;    '  →  &#039;
     *
     * KURAL: Ekrana basılan HER dinamik değer bu fonksiyondan geçmeli.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * Uygulama içi adres üretir.
     *
     * pretty_urls kapalıyken (varsayılan):  index.php?r=users
     * açıkken:                              users
     *
     * Varsayılan biçim hiçbir sunucu ayarı gerektirmez; XAMPP'ta
     * indirir indirmez çalışır.
     *
     * @param array<string,string|int> $params Ek sorgu parametreleri
     */
    function url(string $path = 'dashboard', array $params = []): string
    {
        $path = trim($path, '/');
        $base = App\Core\Router::basePath();   // "" veya "/rbac-login-system"

        if (Config::get('app.pretty_urls', false)) {
            /* Temiz adres:  /rbac-login-system/users
             * Bağlantıyı MUTLAK üretmek şart; göreli üretilirse
             * "users/detay" gibi iç içe adreslerde taban kayar. */
            $url = $base . '/' . $path;

            return $params === [] ? $url : $url . '?' . http_build_query($params);
        }

        // Klasik adres:  /rbac-login-system/index.php?r=users
        // Boş yol tanıtım sayfasıdır (bkz. Router::currentPath).
        $query = array_merge(['r' => $path === '' ? 'home' : $path], $params);

        return $base . '/index.php?' . http_build_query($query);
    }
}

if (!function_exists('asset')) {
    /**
     * assets/ altındaki dosyaya adres üretir ve SONUNA SÜRÜM EKLER:
     *
     *     assets/css/admin.css?v=1754732963
     *
     * NEDEN SÜRÜM PARAMETRESİ (cache busting)?
     * Tarayıcılar CSS ve JS dosyalarını önbelleğe alır. Dosyanın adı
     * değişmediği sürece, siz içeriğini güncelleseniz bile ziyaretçi
     * ESKİ sürümü görmeye devam eder — "tasarımım değişmedi" şikâyeti
     * neredeyse her zaman budur.
     *
     * filemtime() dosyanın son değiştirilme zamanını verir. Dosyayı
     * her düzenlediğinizde bu sayı değişir, adres değişir ve tarayıcı
     * dosyayı yeniden indirmek ZORUNDA kalır. Elle sürüm numarası
     * artırmayı unutma riski de ortadan kalkar.
     */
    function asset(string $path): string
    {
        $path = ltrim($path, '/');
        $file = CY_BASE . '/assets/' . $path;

        // Dosya yoksa (yazım hatası) sürüm eklemeden döndür.
        $version = is_file($file) ? (string) filemtime($file) : '';

        /* Adres MUTLAK üretilir. Temiz adres modunda sayfa yolu
         * "/rbac-login-system/users" olduğunda göreli bir "assets/..."
         * bağlantısı tarayıcı tarafından yanlış çözümlenirdi. */
        return App\Core\Router::basePath() . '/assets/' . $path
             . ($version !== '' ? '?v=' . $version : '');
    }
}

if (!function_exists('csrf_field')) {
    /** Forma gömülecek gizli CSRF alanı. */
    function csrf_field(): string
    {
        return Csrf::field();
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('auth')) {
    /** Giriş yapan kullanıcı (yoksa null). */
    function auth(): ?App\Models\User
    {
        return Auth::user();
    }
}

if (!function_exists('is_route')) {
    /**
     * Sol menüde aktif bağlantıyı işaretlemek için.
     * "users" verildiğinde "users/create" gibi alt sayfalarda da true döner.
     */
    function is_route(string $path): bool
    {
        /* Rotayı Router'dan soruyoruz; burada ikinci bir kopya mantık
         * yazmak, temiz adres modunda menü vurgusunun sessizce
         * kaybolmasına yol açıyordu (Router PATH_INFO da okur). */
        $current = App\Core\Router::currentPath();

        return $current === $path || str_starts_with($current, $path . '/');
    }
}

if (!function_exists('old')) {
    /**
     * Doğrulama hatasından sonra formu kullanıcının girdiği değerlerle
     * yeniden doldurur; sıfırdan yazmak zorunda kalmasın.
     *
     * @param array<string,mixed> $bag
     */
    function old(array $bag, string $key, string $default = ''): string
    {
        $value = $bag[$key] ?? $default;

        return e(is_scalar($value) ? (string) $value : $default);
    }
}

if (!function_exists('current_theme')) {
    /**
     * Bu istekte hangi tema uygulanmalı? ("light" | "dark")
     *
     * SIRALAMA VE NEDENİ:
     *
     *  1) Giriş yapılmışsa → HESABIN tercihi.
     *     Kullanıcı hangi cihazdan girerse girsin kendi düzenini bulur.
     *     Çerez bu durumda dikkate alınmaz; hesap her zaman haklıdır.
     *
     *  2) Giriş yapılmamışsa (giriş ekranı) → çerez.
     *     Henüz kim olduğunu bilmediğimiz ziyaretçinin son tercihi.
     *
     *  3) Hiçbiri yoksa → AÇIK tema.
     *     İşletim sisteminin koyu mod ayarına BİLEREK bakmıyoruz:
     *     varsayılanın öngörülebilir olması, tahmin etmekten iyidir.
     *
     * Tema sunucuda çözülüp ilk HTML'e yazıldığı için sayfa hiçbir
     * zaman yanlış temada açılıp sonra zıplamaz.
     */
    function current_theme(): string
    {
        /* try/catch NEDEN?
         * Bu fonksiyonu HATA SAYFASI da çağırır. Veritabanı erişilemez
         * olduğunda Auth::user() istisna fırlatır; sarmalamazsak
         * "hata sayfası basarken hata" durumuna düşer ve kullanıcı
         * bembeyaz bir ekran görür. Tema kozmetik bir ayrıntıdır;
         * uğruna hata sayfasını kaybetmeye değmez. */
        try {
            $user = Auth::user();

            if ($user !== null) {
                return $user->theme;
            }
        } catch (Throwable) {
            // Sessizce çereze/varsayılana düşüyoruz.
        }

        return ($_COOKIE['cy_theme'] ?? '') === App\Models\User::THEME_DARK
            ? App\Models\User::THEME_DARK
            : App\Models\User::THEME_LIGHT;
    }
}

if (!function_exists('human_date')) {
    /**
     * Tarihi "3 dakika önce" gibi okunur biçime çevirir.
     * 7 günden eskiyse normal tarih gösterilir.
     */
    function human_date(?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            return $value;
        }

        $diff = time() - $timestamp;

        return match (true) {
            $diff < 60        => 'az önce',
            $diff < 3600      => (int) ($diff / 60) . ' dakika önce',
            $diff < 86400     => (int) ($diff / 3600) . ' saat önce',
            $diff < 604800    => (int) ($diff / 86400) . ' gün önce',
            default           => date('d.m.Y H:i', $timestamp),
        };
    }
}

if (!function_exists('icon')) {
    /**
     * Satır içi SVG ikon döndürür.
     *
     * NEDEN İKON FONTU (Font Awesome vb.) KULLANMIYORUZ?
     *  - Ek bir dosya indirmek gerekmez (daha hızlı açılış)
     *  - Dış CDN'e bağımlılık yok (internetsiz de çalışır, CSP dostu)
     *  - Renk ve boyut CSS ile doğrudan kontrol edilir
     *
     * Tüm ikonlar 24x24 kutuda, "stroke" (çizgi) tarzındadır.
     */
    function icon(string $name, string $class = 'cy-icon'): string
    {
        $paths = [
            'dashboard' => '<path d="M3 13h8V3H3zM13 21h8V11h-8zM13 3v6h8V3zM3 21h8v-6H3z"/>',
            'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
            'user'      => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'shield'    => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9v0a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
            'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
            'plus'      => '<path d="M12 5v14M5 12h14"/>',
            'search'    => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
            'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>',
            'edit'      => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4z"/>',
            'trash'     => '<path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>',
            'menu'      => '<path d="M3 12h18M3 6h18M3 18h18"/>',
            'close'     => '<path d="M18 6 6 18M6 6l12 12"/>',
            'moon'      => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
            'sun'       => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
            'activity'  => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
            'check'     => '<path d="M20 6 9 17l-5-5"/>',
            'alert'     => '<path d="M12 9v4M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>',
            'mail'      => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
            'phone'     => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.9.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
            'lock'      => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
            'calendar'  => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
            'upload'    => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/>',
            'chevron'   => '<path d="m9 18 6-6-6-6"/>',
            'github'    => '<path d="M9 19c-5 1.5-5-2.5-7-3m14 6v-3.87a3.37 3.37 0 0 0-.94-2.61c3.14-.35 6.44-1.54 6.44-7A5.44 5.44 0 0 0 20 4.77 5.07 5.07 0 0 0 19.91 1S18.73.65 16 2.48a13.38 13.38 0 0 0-7 0C6.27.65 5.09 1 5.09 1A5.07 5.07 0 0 0 5 4.77a5.44 5.44 0 0 0-1.5 3.78c0 5.42 3.3 6.61 6.44 7A3.37 3.37 0 0 0 9 18.13V22"/>',
        ];

        $body = $paths[$name] ?? $paths['alert'];

        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor"'
             . ' stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"'
             . ' aria-hidden="true" focusable="false">' . $body . '</svg>';
    }
}
