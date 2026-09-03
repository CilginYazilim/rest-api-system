<?php
/**
 * =====================================================================
 *  Session – Sertleştirilmiş oturum yönetimi
 * ---------------------------------------------------------------------
 *  Düz "session_start()" çağırmak yeterli değildir. Bu sınıf oturumu
 *  başlatırken şu korumaları da devreye alır:
 *
 *   1. httponly  → JavaScript çerezi OKUYAMAZ (XSS ile çerez çalınamaz)
 *   2. samesite  → Başka siteden gelen isteklerde çerez gönderilmez (CSRF)
 *   3. secure    → HTTPS varsa çerez yalnızca şifreli bağlantıda gider
 *   4. use_strict_mode → Saldırganın uydurduğu oturum kimliği kabul edilmez
 *   5. Parmak izi → Tarayıcı/IP değişirse oturum düşürülür (çalıntı çerez)
 *   6. Yenileme  → Oturum kimliği periyodik değişir (session fixation)
 *   7. Zaman aşımı → Uzun süre hareketsiz kalan oturum kapatılır
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        /* Oturum kimliğinin URL'de taşınmasını engelle. URL'de giden
         * bir oturum kimliği, kopyalanan bağlantıyla başkasına sızar. */
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        /* Strict mode: PHP, kendi üretmediği bir oturum kimliğini kabul
         * etmez. "Session fixation" saldırısının temel savunmasıdır. */
        ini_set('session.use_strict_mode', '1');

        session_name((string) Config::get('session.name', 'CYADMINSESS'));

        session_set_cookie_params([
            'lifetime' => 0,          // Tarayıcı kapanınca silinsin
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',      // Normal gezinmede çalışır, POST-CSRF'i keser
        ]);

        session_start();
        self::$started = true;

        self::guard();
    }

    /**
     * Oturumun hâlâ geçerli olup olmadığını denetler.
     * Şüpheli bir durumda oturumu tamamen yok eder.
     */
    private static function guard(): void
    {
        $now         = time();
        $idleTimeout = (int) Config::get('session.idle_timeout', 1800);
        $regenEvery  = (int) Config::get('session.regenerate_every', 900);

        /* --- 1) Parmak izi -------------------------------------------
         * Tarayıcı kimliği (User-Agent) oturum boyunca değişmemelidir.
         * Değiştiyse çerez başka bir cihaza taşınmış olabilir.
         *
         * NOT: IP adresini parmak izine KATMIYORUZ. Mobil şebekelerde
         * IP sık değişir ve kullanıcılar durmadan atılır. Güvenlik ile
         * kullanılabilirlik arasındaki bilinçli bir dengedir. */
        $fingerprint = hash('sha256', ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|cy-admin');

        if (!isset($_SESSION['_fingerprint'])) {
            $_SESSION['_fingerprint'] = $fingerprint;
        } elseif (!hash_equals((string) $_SESSION['_fingerprint'], $fingerprint)) {
            self::destroy();
            session_start();
            $_SESSION['_fingerprint'] = $fingerprint;
        }

        /* --- 2) Hareketsizlik zaman aşımı --------------------------- */
        if (isset($_SESSION['_last_activity'])
            && ($now - (int) $_SESSION['_last_activity']) > $idleTimeout) {

            self::destroy();
            session_start();
            $_SESSION['_expired'] = true;
        }

        $_SESSION['_last_activity'] = $now;

        /* --- 3) Periyodik kimlik yenileme --------------------------- */
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = $now;
        } elseif (($now - (int) $_SESSION['_created']) > $regenEvery) {
            self::regenerate();
        }
    }

    /**
     * Oturum kimliğini yeniler, verileri korur.
     * Giriş/çıkış anında MUTLAKA çağrılmalıdır.
     */
    public static function regenerate(): void
    {
        // true : eski oturum dosyasını da sil (arkada kalıp kullanılmasın)
        session_regenerate_id(true);
        $_SESSION['_created'] = time();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Değeri okur ve hemen siler (tek kullanımlık veriler için).
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    /** Oturumu tamamen yok eder (veri + çerez). */
    public static function destroy(): void
    {
        $_SESSION = [];

        /* session_destroy() tek başına YETMEZ: tarayıcıdaki çerez
         * kalır. Çerezi geçmiş bir tarihle göndererek sildiriyoruz. */
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
    }

    /** Bağlantı HTTPS üzerinden mi geliyor? (proxy başlığı dahil) */
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        // Cloudflare / nginx gibi bir vekil sunucu arkasındaysak
        return (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
    }
}
