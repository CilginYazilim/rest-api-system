<?php
/**
 * =====================================================================
 *  Response – JSON / yönlendirme / güvenlik başlıkları
 * ---------------------------------------------------------------------
 *  JavaScript tarafı her istekte AYNI yanıt biçimini bekler:
 *      { success: bool, type: string, description: string, ... }
 *  Bu sınıf o biçimi garanti eder.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /**
     * Tarayıcıya güvenlik başlıklarını gönderir.
     * index.php'de her istekte bir kez çağrılır.
     */
    public static function securityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        // Tarayıcı içerik türünü "tahmin etmeye" çalışmasın.
        header('X-Content-Type-Options: nosniff');

        // Sayfamız başka bir sitenin <iframe>'ine gömülemesin (clickjacking).
        header('X-Frame-Options: DENY');

        // Dış sitelere giderken tam adresimizi sızdırma.
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // İhtiyacımız olmayan cihaz izinlerini baştan kapat.
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');

        // PHP sürümünü gizle (saldırgana bilgi vermeyelim).
        header_remove('X-Powered-By');

        /* HTML SAYFALARI ÖNBELLEĞE ALINMASIN.
         *
         * İki sebebi var:
         *  1. GÜVENLİK: Çıkış yaptıktan sonra "geri" tuşuna basan biri,
         *     önbellekten gelen sayfada özel verileri görebilirdi.
         *     Ortak kullanılan bilgisayarlarda ciddi bir sızıntıdır.
         *  2. GELİŞTİRME: Kodu güncellediğinizde tarayıcı eski sayfayı
         *     göstermeye devam etmez.
         *
         * (CSS/JS dosyaları bundan etkilenmez; onlar Apache tarafından
         *  servis edilir ve adreslerinde sürüm parametresi taşırlar —
         *  bkz. asset() fonksiyonu.) */
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');

        /* HSTS: Yalnızca HTTPS üzerinden gelen isteklerde anlamlıdır.
         * Tarayıcıya "bu siteye bir daha HTTP ile bağlanma" der. */
        if (Session::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        /* Content-Security-Policy: Sayfaya hangi kaynakların
         * yüklenebileceğini sınırlar. XSS'e karşı ikinci savunma
         * hattıdır (birincisi çıktı kaçışlamasıdır).
         *
         * 'self'          → sadece kendi sunucumuz
         * data:           → görsel önizlemesi base64 ile geliyor
         * unsafe-inline   → Bootstrap ve DataTables satır içi stil üretir;
         *                   SCRIPT için vermiyoruz, tüm JS ayrı dosyada. */
        if (Config::get('security.csp_enabled', true)) {
            header(
                "Content-Security-Policy: "
                . "default-src 'self'; "
                . "script-src 'self'; "
                . "style-src 'self' 'unsafe-inline'; "
                . "img-src 'self' data:; "
                . "font-src 'self' data:; "
                . "connect-src 'self'; "
                . "form-action 'self'; "
                . "frame-ancestors 'none'; "
                . "base-uri 'self'; "
                . "object-src 'none'"
            );
        }
    }

    /**
     * HTTP durum kodunu güvenle gönderir.
     *
     * NEDEN http_response_code() YETMİYOR?
     * Apache, tanımadığı bir durum kodunu açıklamasız (reason phrase)
     * aldığında yanıtı 500'e çeviriyor. 419 kodumuz tam da böyle bir
     * kod: standartta yoktur, Laravel'den gelen bir gelenektir
     * ("oturum/CSRF anahtarı zaman aşımına uğradı").
     *
     * Bunu ölçtük: http_response_code(419) → tarayıcıya 500 olarak
     * ulaşıyordu. JavaScript tarafı oturum düşmesini 419'a bakarak
     * anladığı için, kullanıcı "tekrar giriş yapın" uyarısı yerine
     * anlamsız bir hata görüyordu.
     *
     * Açıklamayı biz yazınca Apache kodu olduğu gibi geçiriyor.
     * Standart kodlar için PHP'nin kendi yolu yeterlidir.
     */
    private static function status(int $code): void
    {
        /** Standartta olmayan, açıklamasını kendimiz yazmamız gereken kodlar. */
        $custom = [
            419 => 'Authentication Timeout',
        ];

        if (isset($custom[$code])) {
            header(sprintf('HTTP/1.1 %d %s', $code, $custom[$code]), true, $code);

            return;
        }

        http_response_code($code);
    }

    /**
     * JSON yanıtı gönderir ve script'i sonlandırır.
     *
     * exit ŞART: Yanıt gönderildikten sonra kod devam edip ikinci bir
     * JSON basarsa JavaScript "Unexpected token" hatası verir.
     *
     * @param array<string,mixed> $payload
     */
    public static function json(array $payload, int $status = 200): never
    {
        if (!headers_sent()) {
            self::status($status);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');

            // API yanıtları önbelleğe alınmasın (eski veri gösterilmesin).
            header('Cache-Control: no-store, no-cache, must-revalidate');
        }

        // JSON_UNESCAPED_UNICODE: Türkçe karakterler ç gibi değil,
        // doğrudan "ç" olarak yazılsın. Hem okunur hem küçük.
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    /** Standart BAŞARI yanıtı. @param array<string,mixed> $extra */
    public static function success(string $description, array $extra = []): never
    {
        self::json(array_merge([
            'success'     => true,
            'type'        => 'success',
            'description' => $description,
        ], $extra));
    }

    /**
     * Standart HATA yanıtı.
     *
     * Kullanılan HTTP kodları:
     *   400 Geçersiz istek · 401 Giriş gerekli · 403 Yetkisiz
     *   404 Bulunamadı     · 419 CSRF/oturum   · 422 Doğrulama hatası
     *   429 Çok fazla istek · 500 Sunucu hatası
     *
     * @param array<string,mixed> $extra
     */
    public static function error(string $description, int $status = 400, array $extra = []): never
    {
        self::json(array_merge([
            'success'     => false,
            'type'        => 'danger',
            'description' => $description,
        ], $extra), $status);
    }

    /**
     * Başka bir adrese yönlendirir.
     *
     * exit ŞART: Location başlığı gönderilse bile PHP kodu çalışmaya
     * devam eder. Yetki kontrolünden sonra exit unutulursa korumalı
     * sayfanın içeriği yine de basılır — klasik ve tehlikeli bir hatadır.
     */
    public static function redirect(string $url, int $status = 302): never
    {
        if (!headers_sent()) {
            http_response_code($status);
            header('Location: ' . $url);
        }

        exit;
    }
}
