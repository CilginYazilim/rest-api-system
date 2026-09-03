<?php
/**
 * =====================================================================
 *  Database – PDO bağlantısını tek noktadan yönetir
 * ---------------------------------------------------------------------
 *  Eski kodda her dosya kendi bağlantısını açıyordu. Burada "tembel
 *  tekil" (lazy singleton) yaklaşımı kullanıyoruz:
 *
 *    - Bağlantı İLK istendiğinde açılır (sayfa DB kullanmıyorsa hiç açılmaz)
 *    - Sonraki her çağrıda AYNI bağlantı döner
 *
 *  Model ve Repository sınıfları PDO nesnesini yapıcı metotlarında
 *  parametre olarak alır (Dependency Injection); böylece test ederken
 *  sahte bir bağlantı verebilirsiniz.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?PDO $connection = null;

    /** Bu sınıfın örneği oluşturulamaz; sadece statik kullanılır. */
    private function __construct()
    {
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) Config::get('db.host'),
            (int) Config::get('db.port', 3306),
            (string) Config::get('db.name'),
            (string) Config::get('db.charset', 'utf8mb4')
        );

        try {
            self::$connection = new PDO(
                $dsn,
                (string) Config::get('db.user'),
                (string) Config::get('db.pass'),
                [
                    /* Sorgu hata verdiğinde istisna fırlat. Varsayılan
                     * ayarda hatalar sessizce yutulur ve saatlerce
                     * "neden çalışmıyor?" diye ararsınız. */
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                    /* Sonuçlar $row['name'] biçiminde gelsin. Varsayılan
                     * hem isimli hem numaralı döndürür, belleği boşa harcar. */
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                    /* Sorguyu gerçekten MySQL hazırlasın (taklit etmesin).
                     * SQL Injection'a karşı en güçlü koruma budur.
                     * DİKKAT: Bu ayar açıkken aynı isimli yer tutucu
                     * (:deger) bir sorguda iki kez kullanılamaz. */
                    PDO::ATTR_EMULATE_PREPARES => false,

                    /* Kalıcı bağlantı kapalı: XAMPP gibi ortamlarda
                     * bağlantı havuzu şişmesine yol açabiliyor. */
                    PDO::ATTR_PERSISTENT => false,
                ]
            );
        } catch (PDOException $e) {
            /* Hata mesajı DSN'i ve dolayısıyla veritabanı adını içerebilir.
             * Bu yüzden ayrıntıyı sadece geliştirme modunda gösteriyoruz. */
            error_log('[CY] Veritabani baglanti hatasi: ' . $e->getMessage());

            throw new RuntimeException(
                Config::get('app.debug')
                    ? 'Veritabanı bağlantı hatası: ' . $e->getMessage()
                    : 'Veritabanına bağlanılamadı. Lütfen daha sonra tekrar deneyin.',
                0,
                $e
            );
        }

        return self::$connection;
    }

    /** Bağlantıyı kapatır (uzun süren CLI betikleri için). */
    public static function disconnect(): void
    {
        self::$connection = null;
    }
}
