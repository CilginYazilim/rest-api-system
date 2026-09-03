<?php
/**
 * =====================================================================
 *  Autoloader – Sınıfları otomatik yükleyici (PSR-4 mantığı)
 * ---------------------------------------------------------------------
 *  Eskiden her dosyayı elle require etmek gerekiyordu:
 *      require 'app/Models/User.php';
 *
 *  Artık gerek yok. PHP tanımadığı bir sınıfla karşılaşınca aşağıdaki
 *  fonksiyonu çağırır, biz de isim alanından (namespace) dosya yolunu
 *  hesaplayıp yükleriz:
 *
 *      App\Models\User      →  app/Models/User.php
 *      App\Core\Database    →  app/Core/Database.php
 *
 *  Composer kullanmıyoruz; bu proje bilerek SIFIR BAĞIMLILIKLIDIR,
 *  indirip doğrudan çalıştırabilmeniz için.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Autoloader
{
    /**
     * @param string $prefix  Kök isim alanı (örn. "App\")
     * @param string $baseDir Bu isim alanının karşılık geldiği klasör
     */
    public static function register(string $prefix, string $baseDir): void
    {
        $prefix  = rtrim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, '/\\') . DIRECTORY_SEPARATOR;

        spl_autoload_register(static function (string $class) use ($prefix, $baseDir): void {

            // Bu sınıf bizim isim alanımıza ait değilse karışma;
            // başka bir autoloader ilgilenecektir.
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            // "App\Models\User" → "Models\User"
            $relative = substr($class, strlen($prefix));

            // "Models\User" → "Models/User.php"
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';

            /* GÜVENLİK: realpath() ile dosyanın gerçekten kendi
             * klasörümüzün içinde olduğunu doğruluyoruz. Böylece
             * uydurma bir sınıf adı ("App\..\..\etc\passwd") ile
             * klasör dışına çıkılamaz (path traversal koruması). */
            $real = realpath($file);

            if ($real !== false && str_starts_with($real, realpath($baseDir) ?: $baseDir)) {
                require $real;
            }
        });
    }
}
