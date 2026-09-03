<?php
/**
 * =====================================================================
 *  Flash – Tek seferlik bildirim mesajları
 * ---------------------------------------------------------------------
 *  "Kayıt eklendi" gibi mesajları yönlendirmeden SONRA göstermek için
 *  kullanılır. Oturuma yazılır, bir kez okunur ve silinir.
 *
 *  NEDEN GEREKLİ?  Form gönderiminden sonra doğrudan sayfa basmak
 *  yerine yönlendirme yaparız (POST → Redirect → GET kalıbı). Böylece
 *  kullanıcı F5'e bastığında form tekrar gönderilmez. Ama yönlendirme
 *  değişkenleri sıfırlar; mesajı taşımak için Flash gerekir.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

final class Flash
{
    private const KEY = '_flash';

    /**
     * @param string $type success | danger | warning | info
     */
    public static function add(string $type, string $message): void
    {
        $_SESSION[self::KEY][] = ['type' => $type, 'message' => $message];
    }

    public static function success(string $message): void
    {
        self::add('success', $message);
    }

    public static function error(string $message): void
    {
        self::add('danger', $message);
    }

    public static function warning(string $message): void
    {
        self::add('warning', $message);
    }

    public static function info(string $message): void
    {
        self::add('info', $message);
    }

    /**
     * Tüm mesajları döndürür ve kuyruğu temizler.
     *
     * @return array<int,array{type:string,message:string}>
     */
    public static function pull(): array
    {
        $messages = $_SESSION[self::KEY] ?? [];
        unset($_SESSION[self::KEY]);

        return is_array($messages) ? $messages : [];
    }

    /**
     * Form hatalarını ve girilen değerleri yönlendirme sonrasına taşır.
     * Böylece kullanıcı formu baştan doldurmak zorunda kalmaz.
     *
     * @param array<string,string> $errors
     * @param array<string,mixed>  $old
     */
    public static function withInput(array $errors, array $old = []): void
    {
        $_SESSION['_errors'] = $errors;
        $_SESSION['_old']    = $old;
    }

    /** @return array<string,string> */
    public static function errors(): array
    {
        $errors = $_SESSION['_errors'] ?? [];
        unset($_SESSION['_errors']);

        return is_array($errors) ? $errors : [];
    }

    /** @return array<string,mixed> */
    public static function old(): array
    {
        $old = $_SESSION['_old'] ?? [];
        unset($_SESSION['_old']);

        return is_array($old) ? $old : [];
    }
}
