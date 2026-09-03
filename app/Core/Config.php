<?php
/**
 * =====================================================================
 *  Config – Ayarlara nokta notasyonuyla erişim
 * ---------------------------------------------------------------------
 *      Config::load(CY_BASE . '/config/config.php');
 *      Config::get('db.host');            // 127.0.0.1
 *      Config::get('upload.max_bytes');   // 2097152
 *      Config::get('yok.olan', 'yedek');  // yedek
 *
 *  NEDEN SINIF?  Ayarlar tek yerden okunur, testte kolayca
 *  değiştirilebilir ve "define()" gibi tüm global alanı kirletmez.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    /** Ayar dosyasını (dizi döndüren PHP dosyası) yükler. */
    public static function load(string $file): void
    {
        $data = require $file;

        self::$items = is_array($data) ? $data : [];
    }

    /**
     * "a.b.c" yolunu izleyerek değeri getirir.
     * Yol üzerinde herhangi bir adım yoksa $default döner.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /** Çalışma anında ayar değiştirmek için (çoğunlukla testlerde). */
    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref      = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                $ref[$segment] = [];
            }

            $ref = &$ref[$segment];
        }

        $ref = $value;
    }
}
