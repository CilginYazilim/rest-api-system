<?php
/**
 * =====================================================================
 *  Env – ".env" dosyası okuyucu
 * ---------------------------------------------------------------------
 *  Şifre gibi hassas değerleri koda yazmak yerine, depoya gönderilmeyen
 *  ".env" dosyasında tutarız. Bu sınıf o dosyayı bir kez okuyup
 *  belleğe alır.
 *
 *  Arama sırası:  .env dosyası  →  sunucu ortam değişkeni  →  varsayılan
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Env
{
    /** @var array<string,string>|null Okunan değerler (bir kez doldurulur) */
    private static ?array $values = null;

    /**
     * .env dosyasını okur. Dosya yoksa sessizce geçer; bu durumda
     * yalnızca sunucu ortam değişkenleri ve varsayılanlar kullanılır.
     */
    public static function load(string $path): void
    {
        self::$values = [];

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        // FILE_IGNORE_NEW_LINES : satır sonlarını atar
        // FILE_SKIP_EMPTY_LINES : boş satırları atlar
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Yorum satırları (#) ve "=" içermeyen satırlar atlanır.
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }

            // Yalnızca İLK "=" işaretinden böl; değerin içinde "=" olabilir.
            [$key, $value] = explode('=', $line, 2);

            $key   = trim($key);
            $value = trim($value);

            // Tırnak içine alınmış değerlerin tırnaklarını kaldır.
            if (strlen($value) > 1) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];

                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$values[$key] = $value;
        }
    }

    /**
     * Değeri okur. Bulunamazsa $default döner.
     */
    public static function get(string $key, string $default = ''): string
    {
        if (self::$values !== null && array_key_exists($key, self::$values)) {
            return self::$values[$key];
        }

        $fromServer = getenv($key);

        // getenv() tanımsız değişkende false döner; "" (boş) geçerli bir değerdir.
        return $fromServer !== false ? (string) $fromServer : $default;
    }

    /**
     * "true", "1", "yes", "on" değerlerini true kabul eder.
     * .env dosyasındaki her şey METİNDİR; bu yüzden bu dönüşüm gerekir.
     */
    public static function bool(string $key, bool $default = false): bool
    {
        $raw = strtolower(trim(self::get($key, $default ? 'true' : 'false')));

        return in_array($raw, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Sunucunun YEREL bir geliştirme ortamı olup olmadığını söyler.
     *
     * NEDEN GEREKLİ?
     * ".env" dosyası .gitignore içindedir; depoyu klonlayan biri onu
     * oluşturmadan uygulamayı canlıya atarsa APP_DEBUG için bir değer
     * bulunamaz. Varsayılan "true" olsaydı canlı sunucuda hata yığını,
     * dosya yolları ve tablo adları ziyaretçiye görünürdü.
     *
     * Bu yüzden varsayılanı sabit yazmak yerine ortamdan türetiyoruz:
     * yerelde açık, her yerde kapalı. .env içinde APP_DEBUG yazılmışsa
     * o değer bunu geçersiz kılar; karar hâlâ geliştiricinindir.
     */
    public static function isLocalHost(): bool
    {
        // CLI (worker, zamanlayıcı) için HTTP_HOST yoktur; geliştirme
        // makinesinde çalıştığı varsayılmaz, güvenli taraf seçilir.
        $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

        if ($host === '') {
            return false;
        }

        // Olası port numarasını at:  "localhost:8080" → "localhost"
        $host = explode(':', $host, 2)[0];

        return in_array($host, ['localhost', '127.0.0.1', '::1', '[::1]'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.localhost');
    }
}
