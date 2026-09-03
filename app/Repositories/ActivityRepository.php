<?php
/**
 * =====================================================================
 *  ActivityRepository – İşlem günlüğü (audit log)
 * ---------------------------------------------------------------------
 *  "Bu kaydı kim sildi?" sorusunun cevabı olmayan bir yönetim paneli
 *  eksiktir. Her önemli işlem burada kayıt altına alınır.
 *
 *  Günlük kayıtları SİLİNMEZ ve DEĞİŞTİRİLMEZ; sadece eklenir.
 *  Kullanıcı silindiğinde bile geçmişi kalsın diye user_id alanı
 *  ON DELETE SET NULL ile tanımlanmıştır.
 * =====================================================================
 */

declare(strict_types=1);

namespace App\Repositories;

use PDO;

final class ActivityRepository
{
    /* İşlem türleri. Sabit kullanmak, yazım hatasını derleme anında
     * yakalanır hale getirir ve arayüzde ikon eşlemesini kolaylaştırır. */
    public const LOGIN          = 'login';
    public const LOGOUT         = 'logout';
    public const LOGIN_FAILED   = 'login_failed';
    public const PASSWORD_CHANGE = 'password_changed';

    public function __construct(private PDO $db)
    {
    }

    /**
     * Günlüğe bir satır ekler.
     *
     * @param int|null $userId İşlemi yapan kullanıcı (giriş hatasında null)
     */
    public function log(?int $userId, string $action, string $description, string $ip = ''): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO activity_log (user_id, action, description, ip, created_at)
             VALUES (:user_id, :action, :description, :ip, NOW())'
        );

        $stmt->execute([
            ':user_id'     => $userId,
            ':action'      => $action,
            // Sütunu taşırmamak için kırp; uzun mesaj hataya yol açmasın.
            ':description' => mb_substr($description, 0, 255),
            ':ip'          => mb_substr($ip, 0, 45),
        ]);
    }

    /**
     * Son işlemler (kontrol paneli listesi için).
     *
     * LEFT JOIN kullanıyoruz: kullanıcı silinmiş olsa bile günlük
     * satırı listede görünmeye devam etsin.
     *
     * SIRALAMA NEDEN created_at?
     * "id DESC" ile sıralamak kolaydır ama yanlıştır: örnek veri geçmiş
     * tarihlerle yüklenir, geri alınan işler ve toplu içe aktarımlar da
     * eklenme sırasıyla aynı gitmez. Başlık "Son İşlemler" diyorsa liste
     * ZAMANA göre sıralanmalıdır. Eşit zamanlarda id ikinci ölçüt olur;
     * böylece sıra her sorguda aynı kalır (kararlı sıralama).
     *
     * @return array<int,array<string,mixed>>
     */
    public function latest(int $limit = 8): array
    {
        $limit = max(1, min($limit, 100));

        $stmt = $this->db->query(
            'SELECT a.id, a.action, a.description, a.ip, a.created_at,
                    u.id AS user_id, u.name, u.surname
               FROM activity_log a
               LEFT JOIN users u ON u.id = a.user_id
              ORDER BY a.created_at DESC, a.id DESC
              LIMIT ' . $limit
        );

        return $stmt->fetchAll();
    }

    /** Belirli bir kullanıcının geçmişi. @return array<int,array<string,mixed>> */
    public function forUser(int $userId, int $limit = 10): array
    {
        $limit = max(1, min($limit, 100));

        $stmt = $this->db->prepare(
            'SELECT id, action, description, ip, created_at
               FROM activity_log
              WHERE user_id = :id
              ORDER BY created_at DESC, id DESC
              LIMIT ' . $limit
        );
        $stmt->execute([':id' => $userId]);

        return $stmt->fetchAll();
    }

    /** 90 günden eski kayıtları temizler (bakım işi). */
    public function prune(int $days = 90): void
    {
        $stmt = $this->db->prepare(
            'DELETE FROM activity_log WHERE created_at < (NOW() - INTERVAL :days DAY)'
        );
        $stmt->execute([':days' => $days]);
    }
}
