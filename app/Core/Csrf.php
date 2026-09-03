<?php
/**
 * =====================================================================
 *  Csrf – Sahte istek (Cross-Site Request Forgery) koruması
 * ---------------------------------------------------------------------
 *  CSRF NEDİR?
 *  Panele giriş yapmışken kötü niyetli bir siteyi ziyaret edersiniz.
 *  O site arka planda bizim sunucumuza "kullanıcıyı sil" isteği
 *  gönderir. Tarayıcı çerezlerinizi otomatik eklediği için sunucu
 *  bunu SİZİN yaptığınızı sanır.
 *
 *  ÇÖZÜM: Her oturuma özel, tahmin edilemez bir anahtar üretiriz ve
 *  veri değiştiren her isteğin bu anahtarı taşımasını şart koşarız.
 *  Başka bir site tarayıcının "same-origin policy" kuralı yüzünden
 *  sayfamızı okuyup anahtarı öğrenemez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_token';

    /**
     * Oturuma bağlı anahtarı döndürür (yoksa üretir).
     *
     * random_bytes() kriptografik olarak güvenlidir.
     * rand() / mt_rand() KULLANMAYIN, çıktıları tahmin edilebilir.
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY];
    }

    /**
     * Gelen isteğin anahtarını doğrular.
     *
     * Anahtar iki yerden okunabilir:
     *   - POST verisi  (normal form gönderimi)
     *   - X-CSRF-Token başlığı (saf AJAX istekleri)
     *
     * hash_equals() NEDEN?
     *   "===" karşılaştırması ilk farklı karakterde durur. Saldırgan
     *   yanıt SÜRESİNİ ölçerek anahtarı karakter karakter tahmin
     *   edebilir ("timing attack"). hash_equals() her zaman aynı
     *   sürede çalışarak bunu engeller.
     */
    public static function check(?string $token = null): bool
    {
        $token ??= (string) ($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

        if ($token === '' || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals((string) $_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * Anahtarı yeniler. Giriş ve çıkış anında çağrılır; böylece
     * oturum devralınsa bile eski anahtar işe yaramaz.
     */
    public static function rotate(): void
    {
        unset($_SESSION[self::SESSION_KEY]);
        self::token();
    }

    /** Forma doğrudan gömülebilecek gizli alanı üretir. */
    public static function field(): string
    {
        return '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '">';
    }
}
