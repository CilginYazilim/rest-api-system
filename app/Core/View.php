<?php
/**
 * =====================================================================
 *  View – Basit şablon motoru
 * ---------------------------------------------------------------------
 *  PHP zaten bir şablon dilidir; Blade/Twig gibi bir kütüphaneye
 *  ihtiyacımız yok. Bu sınıf sadece iki şeyi düzenler:
 *
 *    1. Görünüm dosyasını yalıtılmış bir kapsamda çalıştırır
 *       (görünümün içinde $this veya rastgele değişkenler görünmez)
 *    2. Çıktıyı tampona alıp bir düzene (layout) yerleştirir
 *
 *  KULLANIMI:
 *      View::render('users/index', ['users' => $users]);
 *      → views/users/index.php dosyasını çalıştırır
 *      → çıktıyı views/layouts/admin.php içindeki $content'e koyar
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    /** @var array<string,mixed> Tüm görünümlerde erişilebilen veriler */
    private static array $shared = [];

    /**
     * Her görünümde lazım olan verileri (giriş yapan kullanıcı, menü…)
     * tek tek geçirmemek için burada paylaşırız.
     *
     * @param array<string,mixed> $data
     */
    public static function share(array $data): void
    {
        self::$shared = array_merge(self::$shared, $data);
    }

    /**
     * Görünümü düzenle birlikte basar.
     *
     * @param string              $view   "users/index" gibi yol (uzantısız)
     * @param array<string,mixed> $data   Görünüme aktarılacak değişkenler
     * @param string|null         $layout "layouts/admin" · null → düzensiz bas
     */
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/admin'): void
    {
        $content = self::capture($view, $data);

        if ($layout === null) {
            echo $content;
            return;
        }

        // Düzen dosyası, görünümün çıktısını $content değişkeninde bulur.
        echo self::capture($layout, array_merge($data, ['content' => $content]));
    }

    /**
     * Görünümü çalıştırır ve çıktısını METİN olarak döndürür
     * (ekrana basmaz). Parça (partial) görünümler için de kullanılır.
     *
     * @param array<string,mixed> $data
     */
    public static function capture(string $view, array $data = []): string
    {
        $file = CY_BASE . '/views/' . str_replace(['..', '\\'], '', $view) . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('Görünüm bulunamadı: ' . $view);
        }

        /* extract(): Dizi anahtarlarını değişkene çevirir.
         * ['title' => 'X'] → $title = 'X'
         * EXTR_SKIP: Var olan bir değişkeni EZMEZ ($file, $data gibi
         * iç değişkenlerimizin kazara değiştirilmesini önler. */
        extract(array_merge(self::$shared, $data), EXTR_SKIP);

        // ob_start(): Bu noktadan sonraki çıktıyı ekrana değil belleğe yaz.
        ob_start();

        try {
            require $file;
        } catch (\Throwable $e) {
            // Hata olursa yarım çıktıyı temizle, hatayı yukarı ilet.
            ob_end_clean();
            throw $e;
        }

        return (string) ob_get_clean();
    }

    /**
     * Parça görünümü doğrudan basar (sidebar, topbar gibi).
     *
     * @param array<string,mixed> $data
     */
    public static function partial(string $view, array $data = []): void
    {
        echo self::capture($view, $data);
    }
}
