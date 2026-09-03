<?php
/**
 * =====================================================================
 *  RateLimiter – Kaba kuvvet (brute force) saldırısı koruması
 * ---------------------------------------------------------------------
 *  Bir saldırgan giriş formuna saniyede yüzlerce parola deneyebilir.
 *  Bu sınıf hatalı denemeleri sayar ve sınır aşılınca hesabı geçici
 *  olarak kilitler.
 *
 *  NEDEN OTURUMDA DEĞİL DE VERİTABANINDA SAYIYORUZ?
 *  Sayaç oturumda tutulsaydı saldırgan her istekte çerezini silerek
 *  sayacı sıfırlardı. Veritabanı sayacı bundan etkilenmez.
 *
 *  SAYAÇ ANAHTARI: e-posta + IP birlikte kullanılır.
 *    - Sadece IP olsaydı: aynı ofisten çalışan masum kullanıcılar
 *      birbirini kilitlerdi.
 *    - Sadece e-posta olsaydı: saldırgan bir e-postayı kasten
 *      kilitleyerek kullanıcıyı dışarıda bırakabilirdi (DoS).
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Core;

use PDO;

final class RateLimiter
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * Bu anahtar şu an kilitli mi?
     * Kilitliyse kalan süreyi (saniye) döndürür, değilse 0.
     */
    public function lockedFor(string $key): int
    {
        $max    = (int) Config::get('security.login_max_attempts', 5);
        $window = (int) Config::get('security.login_window', 900);
        $lock   = (int) Config::get('security.login_lockout', 900);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) AS adet, MAX(attempted_at) AS son
               FROM login_attempts
              WHERE identifier = :key
                AND attempted_at >= (NOW() - INTERVAL :window SECOND)'
        );
        $stmt->execute([':key' => $this->hash($key), ':window' => $window]);

        $row = $stmt->fetch() ?: [];

        if ((int) ($row['adet'] ?? 0) < $max) {
            return 0;
        }

        $last     = strtotime((string) ($row['son'] ?? 'now')) ?: time();
        $remaining = ($last + $lock) - time();

        return max(0, $remaining);
    }

    /** Hatalı bir deneme kaydeder. */
    public function hit(string $key, string $ip): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO login_attempts (identifier, ip, attempted_at) VALUES (:key, :ip, NOW())'
        );
        $stmt->execute([':key' => $this->hash($key), ':ip' => $ip]);

        // Ara sıra eski kayıtları temizle (tablo sonsuza kadar büyümesin).
        if (random_int(1, 20) === 1) {
            $this->prune();
        }
    }

    /** Başarılı girişten sonra sayacı sıfırlar. */
    public function clear(string $key): void
    {
        $stmt = $this->db->prepare('DELETE FROM login_attempts WHERE identifier = :key');
        $stmt->execute([':key' => $this->hash($key)]);
    }

    /** Bu anahtar için kalan deneme hakkı. */
    public function remaining(string $key): int
    {
        $max    = (int) Config::get('security.login_max_attempts', 5);
        $window = (int) Config::get('security.login_window', 900);

        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM login_attempts
              WHERE identifier = :key AND attempted_at >= (NOW() - INTERVAL :window SECOND)'
        );
        $stmt->execute([':key' => $this->hash($key), ':window' => $window]);

        return max(0, $max - (int) $stmt->fetchColumn());
    }

    /** 24 saatten eski denemeleri siler. */
    public function prune(): void
    {
        $this->db->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }

    /**
     * Anahtarı özetleyerek saklarız.
     *
     * NEDEN? Sayaç tablosuna düz e-posta yazsaydık, veritabanı sızarsa
     * "hangi hesaplara saldırıldı" bilgisi de sızardı. Özet, aynı
     * anahtarı saymamızı sağlar ama içeriği ele vermez.
     */
    private function hash(string $key): string
    {
        return hash('sha256', mb_strtolower(trim($key)));
    }
}
